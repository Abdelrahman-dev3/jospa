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

class TabbyPaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => 'tabby',
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
                $chargeData = $this->createTabbyCharge($request, $typePage , $remainingAmount, $merchantUrls);
            } catch (\Exception $e) {
                return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
            }

            $paymentUrl = $chargeData['configuration']['available_products']['installments'][0]['web_url']
                ?? $chargeData['configuration']['available_products']['pay_later'][0]['web_url']
                ?? null;
            
            if (!$paymentUrl) {
                return response()->json(['status' => false, 'message' => 'Tabby payment not available'], 422);
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

        session(['tabby_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        try {
            $data = $this->createTabbyCharge($request, $typePage , $remainingAmount);
        } catch (\Exception $e) {
            session()->forget('tabby_payment');
            return redirect()->back()->with('error', $e->getMessage());
        }

        $paymentUrl = $data['configuration']['available_products']['installments'][0]['web_url'] ?? $data['configuration']['available_products']['pay_later'][0]['web_url'] ?? null;
        
        if (!$paymentUrl) {
            throw new \Exception('Tabby payment not available');
        }
        
        return redirect()->away($paymentUrl);

    }

    private function createTabbyCharge(Request $request, string $typePage , float $remainingAmount, ?array $merchantUrls = null)
    {
        $secretKey    = config('tabby.secret_key');
        $merchantCode = config('tabby.merchant_code');
        $baseUrl      = 'https://api.tabby.ai/api/v2/checkout';
    
        if (!$secretKey || !$merchantCode) {
            throw new \Exception('Tabby configuration error');
        }
    
        $user = auth()->user();
        $userId = $user->id;
    
        $calculator = app(\App\Services\Payment\PaymentCalculatorService::class);
        $totalData  = $calculator->calculateTotal($typePage, $request->invoiceCopon);
    
        if (isset($totalData['error'])) {
            throw new \Exception($totalData['error']);
        }
    
        $finalAmount = $remainingAmount;
    
        $cartIds = $totalData['cart_ids'] ?? [];

        $invoiceRef = 'INV-' . implode('-', $cartIds);
    
        $buyerName = $user->full_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $payload = [
            "merchant_code" => $merchantCode,
            "payment" => [
                "amount"      => $finalAmount,
                "currency"    => "SAR",
                "description" => "Invoice #{$invoiceRef}",
                "buyer" => [
                    "phone" => $user->mobile,
                    "name"  => $buyerName,
                ],
            ],
            "order" => [
                "reference_id" => $invoiceRef,
            ],
            "lang" => app()->getLocale() ?? 'en',
            "merchant_urls" => $merchantUrls ?? [
                "success" => route('tabby.success', $invoiceRef),
                "fail" => route('tabby.fail', $invoiceRef),
                "cancel"  => route('tabby.cancel',  $invoiceRef),
            ],
        ];
    
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($baseUrl, $payload);
    
        if (!$response->successful()) {
            throw new \Exception('Tabby payment failed');
        }
    
        $responseData = $response->json();
        $checkoutId = $responseData['id'] ?? $responseData['checkout_id'] ?? null;
        if ($checkoutId) {
            session()->put('tabby_payment.checkout_id', $checkoutId);
        }

        return $responseData;
    }


    public function callback(Request $request)
    {
        $data = session('tabby_payment');
        $subMethodService = app(PaymentSubMethodsService::class);

        if ($data && $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        $checkoutId = $request->get('checkout_id') ?? $request->get('payment_id') ?? ($data['checkout_id'] ?? null);
        if (!$checkoutId) {
            return redirect()->back()->with('error', 'Tabby ID not found');
        }

        $secretKey = config('tabby.secret_key');
        $baseUrl = "https://api.tabby.ai/api/v2/checkout/{$checkoutId}";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->get($baseUrl);

        $charge = $response->json();

        if (!isset($charge['status'])) {
            return redirect()->back()->with('error', 'Unexpected Tabby response: ' . json_encode($charge));
        }

        $status = $charge['status'];

        $finalizer = app(PaymentFinalizerService::class);

        $normalizedStatus = strtolower((string) $status);
        switch ($normalizedStatus) {
            case "captured":
            case "authorized":
            case "approved":
                if ($data) {
                    if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                        session()->forget('tabby_payment');
                        return $this->respondSuccess($request, 'Payment already finalized.');
                    }
                    try {
                        $fakeRequest = new Request([
                            'wallet'    => $data['submethods']['wallet'] ?? false,
                            'loyalty'   => $data['submethods']['loyalty'] ?? false,
                            'gift_code' => $data['submethods']['gift_code'] ?? null,
                        ]);
                        $subResult = $subMethodService->apply(auth()->id(), $fakeRequest, $data['final_before_sub']);
                        if (isset($subResult['error'])) {
                            session()->forget('tabby_payment');
                            return $this->respondFailure($request, $subResult['error'], 422);
                        }
                        $subPayments = array_merge($subResult ?? [], [
                            'gift_code' => $data['submethods']['gift_code'] ?? null,
                        ]);
                        $invoiceId = $finalizer->finalizePayment(
                            auth()->id(),
                            $data['final_before_sub'],
                            $data['tax'],
                            $data['discountAmount'],
                            $data['page'],
                            $data['cart_ids'] ?? [],
                            $data['gift_ids'] ?? [],
                            $data['payment_method'] ?? "Sub Methods",
                            $data['couponCode'] ?? "",
                            true,
                            $subPayments
                        );
                        $subMethodService->apply(auth()->id(), $fakeRequest, $data['final_before_sub'] , true);
        
                        session()->forget('tabby_payment');
                        return $this->respondSuccess($request, 'Payment captured successfully.', [
                            'invoice_id' => $invoiceId ?? null,
                        ]);
                    } catch (\Exception $e) {
                        session()->forget('tabby_payment');
                        return $this->respondFailure($request, $e->getMessage(), 500);
                    }
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

                $fakeRequest = new Request([
                    'wallet'    => $context['wallet'],
                    'loyalty'   => $context['loyalty'],
                    'gift_code' => $context['gift_code'],
                ]);
                $subResult = $subMethodService->apply($user->id, $fakeRequest, $totalData['total']);
                if (isset($subResult['error'])) {
                    return $this->respondFailure($request, $subResult['error'], 422);
                }
                $subPayments = array_merge($subResult ?? [], [
                    'gift_code' => $context['gift_code'],
                ]);
                $finalizer->finalizePayment(
                    $user->id,
                    $totalData['total'],
                    $totalData['tax'],
                    $totalData['discountAmount'],
                    $context['page'],
                    $totalData['cart_ids'] ?? [],
                    $totalData['gift_ids'] ?? [],
                    'tabby',
                    $context['couponCode'] ?? "",
                    true,
                    $subPayments
                );
                $subMethodService->apply($user->id, $fakeRequest, $totalData['total'], true);

                return $this->respondSuccess($request, 'Payment captured successfully.');
            case "failed":
            case "cancelled":
                session()->forget('tabby_payment');
                return $this->respondFailure($request, $status, 402);
            default:
                session()->forget('tabby_payment');
                return $this->respondFailure($request, 'Unknown status: ' . $status, 400);
        }
    }

    public function fail(Request $request, $invoice = null)
    {
        session()->forget('tabby_payment');
        return $this->respondFailure($request, __('messages.payment_failed'), 402);
    }

    public function cancel(Request $request, $invoice = null)
    {
        session()->forget('tabby_payment');
        return $this->respondFailure($request, __('messages.payment_cancelled'), 400);
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
            'invoice' => 'INV-' . implode('-', $data['cart_ids'] ?? []),
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

        $successRoute = $this->wantsJson($request) ? 'api.tabby.success' : 'tabby.success';
        $failRoute = $this->wantsJson($request) ? 'api.tabby.fail' : 'tabby.fail';
        $cancelRoute = $this->wantsJson($request) ? 'api.tabby.cancel' : 'tabby.cancel';

        $successUrl = URL::temporarySignedRoute(
            $successRoute,
            now()->addMinutes(30),
            $params
        );

        return [
            "success" => $successUrl,
            "fail" => route($failRoute, $params['invoice'] ?? null),
            "cancel"  => route($cancelRoute, $params['invoice'] ?? null),
        ];
    }

    private function resolveStatelessContext(Request $request): ?array
    {
        if (! $request->hasValidSignatureWhileIgnoring(['checkout_id', 'payment_id', 'status', 'payment_status', 'order_id'])) {
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
