<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use App\Models\User;

class TamaraPaymentStrategy extends BasePaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $prepared = $this->preparePaymentFlow($request, $typePage, 'tamara');
        if (isset($prepared['response'])) {
            return $prepared['response'];
        }

        $data = $prepared['data'];
        $subResult = $prepared['subResult'];
        $remainingAmount = $prepared['remainingAmount'];

        if ($remainingAmount <= 0) {
            try {
                $this->commitFinalizedPayment($data['user_id'], $request, $data, $subResult);
                return $this->respondSubMethodOnlySuccess($request, $data);
            } catch (\Throwable $e) {
                return $this->respondPayException($request, $e);
            }
        }

        [, $data] = $this->createPaymentAttempt($request, $data, 'tamara', $remainingAmount);

        if ($request->expectsJson()) {
            $merchantUrls = $this->buildSignedMerchantUrls($request, $typePage, $data);
            try {
                $paymentUrl = $this->createTamaraCheckout($remainingAmount, $merchantUrls, [
                    'cart_ids' => $data['cart_ids'] ?? [],
                    'platform' => $request->get('platform'),
                    'is_mobile' => $request->boolean('is_mobile'),
                    'attempt_id' => $data['attempt_id'] ?? null,
                ]);
            } catch (\Exception $e) {
                $this->markPaymentAttemptFailed($data['attempt_id'] ?? null, $e->getMessage());
                return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
            }
            
            if (!$paymentUrl) {
                return response()->json(['status' => false, 'message' => 'Tamara payment not available'], 422);
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Redirect to payment gateway.',
                'data' => [
                    'payment_url' => $paymentUrl,
                    'amount' => $remainingAmount,
                    'payment_method' => $data['payment_method'],
                    'discount_amount' => $data['discountAmount'] ?? 0,
                ],
            ]);
        }

        session(['tamara_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        try {
            $paymentUrl = $this->createTamaraCheckout($remainingAmount);
        } catch (\Exception $e) {
            $this->markPaymentAttemptFailed(session('tamara_payment.attempt_id'), $e->getMessage());
            session()->forget('tamara_payment');
            return redirect()->back()->with('error', $e->getMessage());
        }
        
        if (!$paymentUrl) {
            throw new \Exception('Tamara payment not available');
        }
        
        return redirect()->away($paymentUrl);
    }

    private function createTamaraCheckout(float $amount, ?array $merchantUrlOverrides = null, ?array $context = null): string
    {
        $user = auth()->user();
        
        $secretKey = config('tamara.secret_key');
        $baseUrl   = config('tamara.base_url', 'https://api.tamara.co');
        $currency  = 'SAR';
    
        if (!$secretKey) {
            throw new \Exception('Tamara configuration error');
        }

        $session = $context ?: session('tamara_payment');
        if (!$session) {
            throw new \Exception('Tamara session missing');
        }

        $cartIds    = $session['cart_ids'] ?? [];
        if (empty($cartIds)) {
            throw new \Exception('Tamara cart ids missing');
        }
        $invoiceRef = 'INV-' . implode('-', $cartIds);
    
        $consumerName = $user->full_name ?? $user->username ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $payload = [
            'total_amount' => [
                'amount'   => round($amount, 2),
                'currency' => $currency,
            ],
            'shipping_amount' => [
                'amount'   => 0,
                'currency' => $currency,
            ],
            'tax_amount' => [
                'amount'   => 0,
                'currency' => $currency,
            ],
    
            'order_reference_id' => $invoiceRef,
            'order_number'       => $invoiceRef,
            'description'        => "Invoice #{$invoiceRef}",
            'country_code'       => 'SA',
            'payment_type'       => 'PAY_BY_INSTALMENTS',
    
            'consumer' => [
                'first_name'   => $consumerName,
                'phone_number' => $user->mobile,
            ],
    
            'merchant_url' => $merchantUrlOverrides ?? [
                'success' => route('tamara.success'),
                'failure' => route('tamara.failure'),
                'cancel'  => route('tamara.cancel'),
            ],
    
            'platform'  => $context['platform'] ?? 'web',
            'is_mobile' => (bool) ($context['is_mobile'] ?? false),
        ];
    
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($baseUrl . '/checkout', $payload);


    
        if (!$response->successful()) {
            throw new \Exception('Tamara checkout failed: ' . $response->body());
        }
    
        $data = $response->json();
    
        if (empty($data['checkout_url'])) {
            throw new \Exception('Tamara checkout URL not found');
        }
    
        session()->put('tamara_payment.checkout_id', $data['checkout_id'] ?? null);

        // Persist Tamara's order_id — required for the GET /orders/{order_id} verification call.
        $tamaraOrderId = $data['order_id'] ?? null;
        if ($tamaraOrderId) {
            session()->put('tamara_payment.tamara_order_id', $tamaraOrderId);
        }

        $this->markPaymentAttemptPending((int) ($context['attempt_id'] ?? session('tamara_payment.attempt_id')), [
            'merchant_reference'  => $invoiceRef,
            'gateway_checkout_id' => $data['checkout_id'] ?? null,
            'gateway_order_id'    => $tamaraOrderId ?? $invoiceRef,
            'gateway_response'    => $data,
        ]);

        return $data['checkout_url'];
    }

    public function success(Request $request)
    {
        $data = session('tamara_payment');

        if ($data && $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        // Prefer Tamara's order_id for the GET /orders/{order_id} verification endpoint.
        // Falls back to checkout_id for backwards compatibility.
        $tamaraOrderId = $request->order_id
            ?? $request->orderId
            ?? ($data['tamara_order_id'] ?? null)
            ?? session('tamara_payment.tamara_order_id')
            ?? $request->checkout_id
            ?? ($data['checkout_id'] ?? null);

        if (!$tamaraOrderId) {
            $this->markPaymentAttemptFailed($this->resolveAttemptId($request, $data), 'Tamara order id missing', [
                'callback_payload' => $request->all(),
            ]);
            return $this->respondFailure($request, 'Tamara order id missing', 400);
        }

        // Alias so all audit/logging references ($checkoutId) below continue to work without undefined variable error.
        $checkoutId = $tamaraOrderId;

        // Correct verification endpoint: GET /orders/{order_id}
        $response = Http::withToken(config('tamara.secret_key'))
            ->acceptJson()
            ->get(rtrim(config('tamara.base_url', 'https://api.tamara.co'), '/') . '/orders/' . $tamaraOrderId);

        if (!$response->successful()) {
            $this->markPaymentAttemptFailed($this->resolveAttemptId($request, $data), 'Failed to verify Tamara payment', [
                'gateway_order_id' => $tamaraOrderId,
                'callback_payload' => $request->all(),
            ]);
            return $this->respondFailure($request, 'Failed to verify Tamara payment', 502);
        }

        $checkout = $response->json();
        $status   = $checkout['status'] ?? null;

        if ($status !== 'approved') {
            if ((string) $status === 'cancelled') {
                $this->markPaymentAttemptCancelled($this->resolveAttemptId($request, $data), __('messages.payment_cancelled'), [
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $checkout,
                    'callback_payload' => $request->all(),
                ]);
            } else {
                $this->markPaymentAttemptFailed($this->resolveAttemptId($request, $data), __('messages.payment_failed'), [
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $checkout,
                    'callback_payload' => $request->all(),
                ]);
            }
            session()->forget('tamara_payment');
            return $this->respondFailure($request, __('messages.payment_failed'), 402);
        }

        if ($data) {
            if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                $this->markPaymentAttemptPaid($this->resolveAttemptId($request, $data), [
                    'gateway_transaction_id' => $checkoutId,
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $checkout,
                    'callback_payload' => $request->all(),
                ]);
                session()->forget('tamara_payment');
                return $this->respondSuccess($request, 'Payment already finalized.');
            }

            $subMethodService = app(PaymentSubMethodsService::class);
            $fakeRequest = new Request($data['submethods']);
            $subResult = $subMethodService->apply(auth()->id(), $fakeRequest, $data['final_before_sub']);
            if (isset($subResult['error'])) {
                $this->markPaymentAttemptFailed($this->resolveAttemptId($request, $data), $subResult['error'], [
                    'gateway_transaction_id' => $checkoutId,
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $checkout,
                    'callback_payload' => $request->all(),
                ]);
                session()->forget('tamara_payment');
                return $this->respondFailure($request, $subResult['error'], 422);
            }
            $this->commitFinalizedPayment(auth()->id(), $fakeRequest, $data, $subResult, [
                'attempt_id' => $data['attempt_id'] ?? null,
                'transaction_id' => $checkoutId,
                'merchant_reference' => $checkout['order_reference_id'] ?? null,
                'checkout_id' => $checkoutId,
                'order_id' => $checkout['order_reference_id'] ?? null,
                'gateway_response' => $checkout,
                'callback_payload' => $request->all(),
            ]);

            session()->forget('tamara_payment');

            return $this->respondSuccess($request, 'Payment captured successfully.');
        }

        $context = $this->resolveStatelessContext($request);
        if (!$context) {
            return $this->respondFailure($request, 'Invalid payment callback.', 400);
        }

        $user = User::find($context['user_id']);
        if (!$user) {
            return $this->respondFailure($request, 'User not found.', 404);
        }

        $calculator = app(PaymentCalculatorService::class);
        $totalData = $calculator->calculateTotal($context['page'], $context['couponCode'], 'tamara', $user->id);
        if (isset($totalData['error'])) {
            return $this->respondFailure($request, $totalData['error'], 422);
        }

        if ($context['discount_amount'] !== null && ! $this->isClientDiscountMatching($context['discount_amount'], $totalData['discountAmount'])) {
            return $this->respondFailure($request, 'Discount amount mismatch.', 422);
        }

        if ($this->isAlreadyFinalized($totalData['cart_ids'] ?? [], $totalData['gift_ids'] ?? [])) {
            $this->markPaymentAttemptPaid($context['attempt_id'] ?? null, [
                'gateway_transaction_id' => $checkoutId,
                'gateway_checkout_id' => $checkoutId,
                'gateway_response' => $checkout,
                'callback_payload' => $request->all(),
            ]);
            return $this->respondSuccess($request, 'Payment already finalized.');
        }

        $subMethodService = app(PaymentSubMethodsService::class);
        $fakeRequest = new Request([
            'wallet' => $context['wallet'],
            'loyalty' => $context['loyalty'],
            'gift_code' => $context['gift_code'],
        ]);
        $subResult = $subMethodService->apply($user->id, $fakeRequest, $totalData['total']);
        if (isset($subResult['error'])) {
            $this->markPaymentAttemptFailed($context['attempt_id'] ?? null, $subResult['error'], [
                'gateway_transaction_id' => $checkoutId,
                'gateway_checkout_id' => $checkoutId,
                'gateway_response' => $checkout,
                'callback_payload' => $request->all(),
            ]);
            return $this->respondFailure($request, $subResult['error'], 422);
        }
        $this->commitFinalizedPayment($user->id, $fakeRequest, [
            'final_before_sub' => $totalData['total'],
            'tax' => $totalData['tax'],
            'discountAmount' => $totalData['discountAmount'],
            'couponDiscountAmount' => $totalData['couponDiscountAmount'] ?? 0,
            'paymentGatewayDiscountAmount' => $totalData['paymentGatewayDiscountAmount'] ?? 0,
            'paymentGatewayDiscountMethod' => $totalData['paymentGatewayDiscountMethod'] ?? null,
            'paymentGatewayDiscountLabel' => $totalData['paymentGatewayDiscountLabel'] ?? null,
            'page' => $context['page'],
            'cart_ids' => $totalData['cart_ids'] ?? [],
            'gift_ids' => $totalData['gift_ids'] ?? [],
            'payment_method' => 'tamara',
            'couponCode' => $context['couponCode'] ?? '',
            'attempt_id' => $context['attempt_id'] ?? null,
        ], $subResult, [
            'attempt_id' => $context['attempt_id'] ?? null,
            'transaction_id' => $checkoutId,
            'merchant_reference' => $checkout['order_reference_id'] ?? null,
            'checkout_id' => $checkoutId,
            'order_id' => $checkout['order_reference_id'] ?? null,
            'gateway_response' => $checkout,
            'callback_payload' => $request->all(),
        ]);

        return $this->respondSuccess($request, 'Payment captured successfully.');
    }

    public function failure()
    {
        $this->markPaymentAttemptFailed($this->resolveAttemptId(request(), session('tamara_payment')), __('messages.payment_failed'), [
            'callback_payload' => request()->all(),
        ]);
        session()->forget('tamara_payment');

        return $this->respondFailure(request(), __('messages.payment_failed'), 402);
    }

    public function cancel()
    {
        $this->markPaymentAttemptCancelled($this->resolveAttemptId(request(), session('tamara_payment')), __('messages.payment_cancelled'), [
            'callback_payload' => request()->all(),
        ]);
        session()->forget('tamara_payment');

        return $this->respondFailure(request(), __('messages.payment_cancelled'), 400);
    }

    private function buildSignedMerchantUrls(Request $request, string $typePage, array $data): array
    {
        $params = array_filter([
            'attempt_id' => $data['attempt_id'] ?? null,
            'user_id' => $data['user_id'],
            'page' => $typePage,
            'coupon_code' => $data['couponCode'] ?? null,
            'wallet' => $request->boolean('wallet') ? 1 : null,
            'loyalty' => $request->boolean('loyalty') ? 1 : null,
            'gift_code' => $request->get('gift_code'),
            'discount_amount' => $request->get('discount_amount', $request->get('discountAmount')),
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        $successRoute = $this->wantsJson($request) ? 'api.tamara.success' : 'tamara.success';
        $failureRoute = $this->wantsJson($request) ? 'api.tamara.failure' : 'tamara.failure';
        $cancelRoute = $this->wantsJson($request) ? 'api.tamara.cancel' : 'tamara.cancel';

        $successUrl = URL::temporarySignedRoute(
            $successRoute,
            now()->addMinutes(30),
            $params
        );

        return [
            'success' => $successUrl,
            'failure' => route($failureRoute, ['attempt_id' => $data['attempt_id'] ?? null]),
            'cancel'  => route($cancelRoute, ['attempt_id' => $data['attempt_id'] ?? null]),
        ];
    }

    private function resolveStatelessContext(Request $request): ?array
    {
        if (! $request->hasValidSignatureWhileIgnoring(['checkout_id', 'status', 'payment_id', 'order_id'])) {
            return null;
        }

        $userId = (int) $request->get('user_id');
        if ($userId <= 0) {
            return null;
        }

        $discountRaw = $request->get('discount_amount', $request->get('discountAmount'));
        $discount = null;
        if ($discountRaw !== null && $discountRaw !== '' && is_numeric($discountRaw)) {
            $discount = (float) $discountRaw;
        }

        return [
            'attempt_id' => (int) $request->get('attempt_id'),
            'user_id' => $userId,
            'page' => $request->get('page', 'cart'),
            'couponCode' => $request->get('coupon_code'),
            'wallet' => $request->boolean('wallet'),
            'loyalty' => $request->boolean('loyalty'),
            'gift_code' => $request->get('gift_code'),
            'discount_amount' => $discount,
        ];
    }

}