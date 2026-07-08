<?php

namespace App\Services\Payment\Strategies;

use App\Services\HyperpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CardPaymentStrategy extends BasePaymentStrategy
{
    private const CACHE_TTL_MINUTES = 40;

    public function pay(Request $request, string $typePage)
    {
        $prepared = $this->preparePaymentFlow($request, $typePage, 'card');

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

        try {
            $hyperpay = app(HyperpayService::class);
            $merchantTransactionId = $this->generateMerchantTransactionId($data['user_id']);
            $callbackUrl = $this->buildShopperResultUrl($request, $data, $merchantTransactionId);
            $checkout = $hyperpay->createCheckout(
                $remainingAmount,
                $merchantTransactionId,
                $callbackUrl,
                $this->buildCustomerData()
            );

            $checkoutId = (string) ($checkout['id'] ?? '');
            if ($checkoutId === '') {
                throw new \RuntimeException(
                    app()->getLocale() === 'ar'
                        ? 'تعذر إنشاء جلسة الدفع في Hyperpay.'
                        : 'Failed to create Hyperpay checkout.'
                );
            }

            Cache::put(
                $this->paymentCacheKey($merchantTransactionId),
                array_merge($data, [
                    'amount' => $remainingAmount,
                    'subResult' => $subResult,
                    'checkout_id' => $checkoutId,
                    'merchant_transaction_id' => $merchantTransactionId,
                    'callback_url' => $callbackUrl,
                ]),
                now()->addMinutes(self::CACHE_TTL_MINUTES)
            );

            $paymentUrl = $this->buildHostedCheckoutUrl($checkoutId, $merchantTransactionId);

            if ($this->wantsJson($request)) {
                return response()->json([
                    'status' => true,
                    'message' => 'Redirect to payment gateway.',
                    'data' => [
                        'payment_url' => $paymentUrl,
                        'checkout_id' => $checkoutId,
                        'merchant_transaction_id' => $merchantTransactionId,
                        'amount' => $remainingAmount,
                        'payment_method' => $data['payment_method'],
                        'discount_amount' => $data['discountAmount'] ?? 0,
                    ],
                ]);
            }

            return redirect()->to($paymentUrl);
        } catch (\Throwable $e) {
            return $this->respondPayException($request, $e);
        }
    }

    public function checkout(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $merchantTransactionId = (string) $request->get('mtid');
        $checkoutId = (string) $request->get('checkoutId');
        $data = Cache::get($this->paymentCacheKey($merchantTransactionId));

        if (! $data || ($data['checkout_id'] ?? null) !== $checkoutId) {
            return $this->respondFailure(
                $request,
                app()->getLocale() === 'ar'
                    ? 'انتهت جلسة الدفع. حاول مرة أخرى.'
                    : 'Payment session expired.',
                410
            );
        }

        $hyperpay = app(HyperpayService::class);

        return view('frontend.payment-status.hyperpay', [
            'widgetScriptUrl' => $hyperpay->widgetScriptUrl($checkoutId),
            'brands' => implode(' ', $hyperpay->brands()),
            'brandLabels' => ['Visa', 'Mastercard', 'Mada'],
            'resultUrl' => $data['callback_url'],
        ]);
    }

    public function callback(Request $request)
    {
        if (! $request->hasValidSignatureWhileIgnoring(['id', 'resourcePath'])) {
            abort(403);
        }

        $resourcePath = (string) $request->get('resourcePath');
        $merchantTransactionId = (string) $request->get('mtid');

        if ($resourcePath === '') {
            return $this->respondFailure(
                $request,
                app()->getLocale() === 'ar'
                    ? 'تعذر التحقق من عملية الدفع.'
                    : 'Unable to verify payment.',
                400
            );
        }

        try {
            $hyperpay = app(HyperpayService::class);
            $payment = $hyperpay->fetchPaymentStatus($resourcePath);
            $resultCode = (string) data_get($payment, 'result.code', '');
            $merchantTransactionId = (string) (data_get($payment, 'merchantTransactionId') ?: $merchantTransactionId);
            $cacheKey = $this->paymentCacheKey($merchantTransactionId);
            $data = Cache::get($cacheKey);

            if (! $data) {
                return $this->respondFailure(
                    $request,
                    app()->getLocale() === 'ar'
                        ? 'انتهت جلسة الدفع. حاول مرة أخرى.'
                        : 'Payment session expired.',
                    410
                );
            }

            if (! $hyperpay->isSuccessfulResult($resultCode)) {
                Cache::forget($cacheKey);

                return $this->respondFailure(
                    $request,
                    (string) data_get(
                        $payment,
                        'result.description',
                        app()->getLocale() === 'ar' ? 'فشلت عملية الدفع.' : 'Payment failed.'
                    ),
                    402
                );
            }

            $expectedAmount = (float) ($data['amount'] ?? 0);
            $paidAmount = (float) data_get($payment, 'amount', 0);

            if ($expectedAmount > 0 && $paidAmount > 0 && abs($expectedAmount - $paidAmount) > 0.01) {
                Cache::forget($cacheKey);

                return $this->respondFailure(
                    $request,
                    app()->getLocale() === 'ar'
                        ? 'قيمة الدفع لا تطابق المبلغ المتوقع.'
                        : 'Paid amount does not match expected amount.',
                    422
                );
            }

            if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                Cache::forget($cacheKey);

                return $this->respondSuccess($request, 'Payment already finalized.', [
                    'payment_id' => data_get($payment, 'id'),
                ]);
            }

            $fakeRequest = new Request([
                'wallet' => data_get($data, 'submethods.wallet', false),
                'loyalty' => data_get($data, 'submethods.loyalty', false),
                'gift_code' => data_get($data, 'submethods.gift_code'),
            ]);

            $this->commitFinalizedPayment(
                (int) $data['user_id'],
                $fakeRequest,
                $data,
                $data['subResult'] ?? []
            );

            Cache::forget($cacheKey);

            return $this->respondSuccess($request, 'Payment successful.', [
                'payment_id' => data_get($payment, 'id'),
                'checkout_id' => $data['checkout_id'] ?? null,
                'amount' => $paidAmount,
            ]);
        } catch (\Throwable $e) {
            return $this->respondPayException($request, $e, 502);
        }
    }

    private function buildHostedCheckoutUrl(string $checkoutId, string $merchantTransactionId): string
    {
        return URL::temporarySignedRoute(
            'hyperpay.checkout',
            now()->addMinutes(30),
            [
                'checkoutId' => $checkoutId,
                'mtid' => $merchantTransactionId,
            ]
        );
    }

    private function buildShopperResultUrl(Request $request, array $paymentData, string $merchantTransactionId): string
    {
        $params = array_filter([
            'user_id' => $paymentData['user_id'] ?? null,
            'coupon_code' => $paymentData['couponCode'] ?? null,
            'wallet' => data_get($paymentData, 'submethods.wallet') ? 1 : null,
            'loyalty' => data_get($paymentData, 'submethods.loyalty') ? 1 : null,
            'gift_code' => data_get($paymentData, 'submethods.gift_code'),
            'payment_method' => 'card',
            'discount_amount' => $request->get('discount_amount', $request->get('discountAmount', $paymentData['discountAmount'] ?? null)),
            'page' => $paymentData['page'] ?? null,
            'mtid' => $merchantTransactionId,
        ], static function ($value) {
            return $value !== null && $value !== '';
        });

        return URL::temporarySignedRoute(
            'hyperpay.callback',
            now()->addMinutes(30),
            $params
        );
    }

    private function buildCustomerData(): array
    {
        $user = auth()->user();
        $givenName = trim((string) ($user->first_name ?? ''));
        $surname = trim((string) ($user->last_name ?? ''));

        return [
            'given_name' => $givenName !== '' ? $givenName : 'Customer',
            'surname' => $surname !== '' ? $surname : ($givenName !== '' ? $givenName : 'Customer'),
            'mobile' => trim((string) ($user->mobile ?? '')),
            'email' => trim((string) ($user->email ?? '')),
            'country' => 'SA',
        ];
    }

    private function generateMerchantTransactionId(int $userId): string
    {
        return 'JOSPA-' . $userId . '-' . Str::upper(Str::random(16));
    }

    private function paymentCacheKey(string $merchantTransactionId): string
    {
        return 'hyperpay_payment_' . $merchantTransactionId;
    }
}
