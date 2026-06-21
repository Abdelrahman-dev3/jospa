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

        if ($request->expectsJson()) {
            $merchantUrls = $this->buildSignedMerchantUrls($request, $typePage, $data);
            try {
                $paymentUrl = $this->createTamaraCheckout($remainingAmount, $merchantUrls, [
                    'cart_ids' => $data['cart_ids'] ?? [],
                    'platform' => $request->get('platform'),
                    'is_mobile' => $request->boolean('is_mobile'),
                ]);
            } catch (\Exception $e) {
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
    
        session()->put('tamara_payment.checkout_id', $data['checkout_id']);
    
        return $data['checkout_url'];
    }

    public function success(Request $request)
    {
        $data = session('tamara_payment');

        if ($data && $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        $checkoutId = $request->checkout_id ?? ($data['checkout_id'] ?? null);

        if (!$checkoutId) {
            return $this->respondFailure($request, 'Tamara checkout id missing', 400);
        }

        $response = Http::withToken(config('tamara.secret_key'))
            ->get(config('tamara.base_url') . "/api/v2/checkout/{$checkoutId}");

        if (!$response->successful()) {
            return $this->respondFailure($request, 'Failed to verify Tamara payment', 502);
        }

        $checkout = $response->json();
        $status   = $checkout['status'] ?? null;

        if ($status !== 'approved') {
            session()->forget('tamara_payment');
            return $this->respondFailure($request, __('messages.payment_failed'), 402);
        }

        if ($data) {
            if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                session()->forget('tamara_payment');
                return $this->respondSuccess($request, 'Payment already finalized.');
            }

            $subMethodService = app(PaymentSubMethodsService::class);
            $fakeRequest = new Request($data['submethods']);
            $subResult = $subMethodService->apply(auth()->id(), $fakeRequest, $data['final_before_sub']);
            if (isset($subResult['error'])) {
                session()->forget('tamara_payment');
                return $this->respondFailure($request, $subResult['error'], 422);
            }
            $this->commitFinalizedPayment(auth()->id(), $fakeRequest, $data, $subResult);

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
        $totalData = $calculator->calculateTotal($context['page'], $context['couponCode'], 'tamara');
        if (isset($totalData['error'])) {
            return $this->respondFailure($request, $totalData['error'], 422);
        }

        if ($context['discount_amount'] !== null && ! $this->isClientDiscountMatching($context['discount_amount'], $totalData['discountAmount'])) {
            return $this->respondFailure($request, 'Discount amount mismatch.', 422);
        }

        if ($this->isAlreadyFinalized($totalData['cart_ids'] ?? [], $totalData['gift_ids'] ?? [])) {
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
        ], $subResult);

        return $this->respondSuccess($request, 'Payment captured successfully.');
    }

    public function failure()
    {
        session()->forget('tamara_payment');

        return $this->respondFailure(request(), __('messages.payment_failed'), 402);
    }

    public function cancel()
    {
        session()->forget('tamara_payment');

        return $this->respondFailure(request(), __('messages.payment_cancelled'), 400);
    }

    private function buildSignedMerchantUrls(Request $request, string $typePage, array $data): array
    {
        $params = array_filter([
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
            'failure' => route($failureRoute),
            'cancel'  => route($cancelRoute),
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
