<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Modules\Booking\Models\Booking;
use App\Models\GiftCard;
use App\Models\User;

class TamaraPaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => 'tamara',
            'couponCode' => $request->invoiceCopon ?? '',
            'submethods' => [
                'wallet' => (bool) $request->wallet,
                'loyalty' => (bool) $request->loyalty,
                'gift_code' => $request->gift_code,
            ],
            'final_before_sub' => $request->total ?? 0,
            'discountAmount' => $request->discountAmount ?? 0,
            'cart_ids' => [],
            'gift_ids' => [],
        ];

        $calculator = app(PaymentCalculatorService::class);
        $totalData  = $calculator->calculateTotal($typePage, $request->invoiceCopon);
        if (isset($totalData['error'])) {
            return redirect()->back()->with('error', $totalData['error']);
        }
        
        $data['final_before_sub'] = $totalData['total'];
        $data['discountAmount'] = $totalData['discountAmount'];
        $data['tax'] = $totalData['tax'];
        $data['cart_ids'] = $totalData['cart_ids'];
        $data['gift_ids'] = $totalData['gift_ids'];

        if ($response = $this->validateClientDiscount($request, $totalData['discountAmount'])) {
            return $response;
        }
        
        $subMethodService = app(PaymentSubMethodsService::class);
        $subResult = $subMethodService->apply($data['user_id'], $request, $data['final_before_sub']);

        if (isset($subResult['error'])) {
            return redirect()->back()->with('error', $subResult['error']);
        }

        $remainingAmount = $subResult['remaining_amount'];

        if ($remainingAmount <= 0) {
            try {
                $finalizer = app(PaymentFinalizerService::class);
                $subPayments = array_merge($subResult ?? [], [
                    'gift_code' => $request->get('gift_code'),
                ]);
                $invoiceId = $finalizer->finalizePayment(
                    $data['user_id'],
                    $data['final_before_sub'],
                    $data['tax'],
                    $data['discountAmount'],
                    $typePage,
                    $totalData['cart_ids'] ?? [],
                    $totalData['gift_ids'] ?? [],
                    $data['payment_method'] ?? "Sub Methods",
                    $data['couponCode'] ?? "",
                    true,
                    $subPayments
                );
                $subMethodService->apply($data['user_id'], $request, $data['final_before_sub'] , true);

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => true,
                        'message' => 'Payment completed using sub methods.',
                        'data' => [
                            'paid' => true,
                            'amount' => $data['final_before_sub'],
                            'payment_method' => $data['payment_method'],
                        ],
                    ]);
                }

                return view('frontend.payment-status.captured');
            } catch (\Exception $e) {
                if ($request->expectsJson()) {
                    return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
                }
                return redirect()->back()->with('error', $e->getMessage());
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
            $subPayments = array_merge($subResult ?? [], [
                'gift_code' => $data['submethods']['gift_code'] ?? null,
            ]);
            app(PaymentFinalizerService::class)->finalizePayment(
                auth()->id(),
                $data['final_before_sub'],
                $data['tax'],
                $data['discountAmount'],
                $data['page'],
                $data['cart_ids'],
                $data['gift_ids'],
                $data['payment_method'] ?? "Sub Methods",
                $data['couponCode'] ?? "",
                true,
                $subPayments
            );

            $subMethodService->apply(
                auth()->id(),
                $fakeRequest,
                $data['final_before_sub'],
                true
            );

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
        $totalData = $calculator->calculateTotal($context['page'], $context['couponCode']);
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
        $subPayments = array_merge($subResult ?? [], [
            'gift_code' => $context['gift_code'],
        ]);
        app(PaymentFinalizerService::class)->finalizePayment(
            $user->id,
            $totalData['total'],
            $totalData['tax'],
            $totalData['discountAmount'],
            $context['page'],
            $totalData['cart_ids'] ?? [],
            $totalData['gift_ids'] ?? [],
            'tamara',
            $context['couponCode'] ?? "",
            true,
            $subPayments
        );

        $subMethodService->apply(
            $user->id,
            $fakeRequest,
            $totalData['total'],
            true
        );

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

    private function isAlreadyFinalized(array $cartIds, array $giftIds): bool
    {
        if (empty($cartIds) && empty($giftIds)) {
            return false;
        }

        if (!empty($cartIds)) {
            $paid = Booking::whereIn('id', $cartIds)->where('payment_status', 1)->count();
            if ($paid !== count($cartIds)) {
                return false;
            }
        }

        if (!empty($giftIds)) {
            $paidGifts = GiftCard::whereIn('id', $giftIds)->where('payment_status', 1)->count();
            if ($paidGifts !== count($giftIds)) {
                return false;
            }
        }

        return true;
    }

    private function validateClientDiscount(Request $request, float $expectedDiscount)
    {
        $clientDiscountRaw = $request->get('discount_amount', $request->get('discountAmount'));
        if ($clientDiscountRaw === null || $clientDiscountRaw === '') {
            return null;
        }

        if (!is_numeric($clientDiscountRaw)) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'Invalid discount amount.'], 422);
            }
            return redirect()->back()->with('error', 'Invalid discount amount.');
        }

        $clientDiscount = (float) $clientDiscountRaw;
        if (abs($clientDiscount - $expectedDiscount) > 0.01) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Discount amount mismatch.',
                    'expected' => $expectedDiscount,
                    'provided' => $clientDiscount,
                ], 422);
            }
            return redirect()->back()->with('error', 'Discount amount mismatch.');
        }

        return null;
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

    private function isClientDiscountMatching(?float $clientDiscount, float $expectedDiscount): bool
    {
        if ($clientDiscount === null) {
            return true;
        }

        return abs($clientDiscount - $expectedDiscount) <= 0.01;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->is('api/*');
    }

    private function respondSuccess(Request $request, string $message, array $data = [])
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data,
            ]);
        }

        return view('frontend.payment-status.captured');
    }

    private function respondFailure(Request $request, string $message, int $status = 400)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return view('frontend.payment-status.failed', ['message' => $message]);
    }

}
