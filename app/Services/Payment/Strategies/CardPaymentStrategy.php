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
            $shopperResultUrl = $this->buildShopperResultUrl($request, $data, $merchantTransactionId);
            $signedCallbackUrl = $this->buildSignedCallbackUrl($request, $data, $merchantTransactionId);
            // Detect selected brand from request to route to correct entity ID
            $brand = strtoupper((string) $request->get('brand', 'VISA'));
            $checkout = $hyperpay->createCheckout(
                $remainingAmount,
                $merchantTransactionId,
                $shopperResultUrl,
                $this->buildCustomerData(),
                $brand
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
                    'amount'                  => $remainingAmount,
                    'subResult'               => $subResult,
                    'checkout_id'             => $checkoutId,
                    'merchant_transaction_id' => $merchantTransactionId,
                    'callback_url'            => $signedCallbackUrl,
                    'hyperpay_brand'          => $brand, // stored so callback uses the correct entity ID
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

        // Show only brands compatible with the entity ID used to create this checkout.
        // VISA/MASTER share one entity; MADA has its own. Mixing them causes 200.300.404 on status fetch.
        $brand = strtoupper((string) ($data['hyperpay_brand'] ?? 'VISA'));
        $isMada = $brand === 'MADA';
        $widgetBrands  = $isMada ? 'MADA' : 'VISA MASTER';
        $widgetLabels  = $isMada ? ['Mada'] : ['Visa', 'Mastercard'];

        return view('frontend.payment-status.hyperpay', [
            'widgetScriptUrl' => $hyperpay->widgetScriptUrl($checkoutId, $brand),
            'brands'          => $widgetBrands,
            'brandLabels'     => $widgetLabels,
            // Must match the shopperResultUrl sent to Hyperpay at checkout creation (plain URL).
            // The signed callback URL is stored in cache and used server-side after Hyperpay redirects.
            'resultUrl'       => route('hyperpay.callback.plain', ['mtid' => $merchantTransactionId]),
        ]);
    }

    /**
     * Plain (unsigned) entry point that Hyperpay redirects to via shopperResultUrl.
     * Hyperpay appends ?resourcePath=...&id=... to this URL.
     * We look up the cached signed callback URL and process payment directly.
     */
    public function callbackPlain(Request $request)
    {
        // DIAGNOSTIC — log all callback parameters
        \Log::error('Hyperpay callbackPlain parameters', $request->all());

        $resourcePath = (string) $request->get('resourcePath', '');
        $merchantTransactionId = (string) $request->get('mtid', '');

        if ($resourcePath === '' || $merchantTransactionId === '') {
            return $this->respondFailure(
                $request,
                app()->getLocale() === 'ar'
                    ? 'تعذر التحقق من عملية الدفع.'
                    : 'Unable to verify payment.',
                400
            );
        }

        // Delegate to the core callback logic using the same mtid + resourcePath
        return $this->processPaymentVerification($request, $resourcePath, $merchantTransactionId);
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

        return $this->processPaymentVerification($request, $resourcePath, $merchantTransactionId);
    }

    private function processPaymentVerification(Request $request, string $resourcePath, string $merchantTransactionId): mixed
    {
        try {
            $hyperpay = app(HyperpayService::class);

            // Load cache first so we know which brand/entity was used at checkout creation
            $cacheKey = $this->paymentCacheKey($merchantTransactionId);
            $data = Cache::get($cacheKey);
            $brand = strtoupper((string) ($data['hyperpay_brand'] ?? 'VISA'));

            // Try to get payment result from callback parameters first
            // Hyperpay sends result directly in the callback for synchronous payments
            $payment = $this->extractPaymentFromCallback($request);

            // If no payment data in callback, fall back to API call
            if (! $payment) {
                $payment = $hyperpay->fetchPaymentStatus($resourcePath, $brand);
            }

            $resultCode = (string) data_get($payment, 'result.code', '');

            // Hyperpay may return the merchantTransactionId in the payment response
            $resolvedMtid = (string) (data_get($payment, 'merchantTransactionId') ?: $merchantTransactionId);
            if ($resolvedMtid !== $merchantTransactionId) {
                $cacheKey = $this->paymentCacheKey($resolvedMtid);
                $data = Cache::get($cacheKey) ?? $data;
                $merchantTransactionId = $resolvedMtid;
            }
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
        // NOTE: Hyperpay's shopperResultUrl must be a plain HTTPS URL.
        // Signed routes with many params are rejected as "invalid parameter".
        // We append only the mtid so we can look up cached data on return.
        // The full signed callback URL is stored in cache and used server-side.
        $configured = rtrim((string) config('services.hyperpay.shopper_result_url', ''), '/');

        if ($configured !== '') {
            // Use the configured base URL with mtid appended as a query param
            return $configured . '?mtid=' . urlencode($merchantTransactionId);
        }

        // Fallback: auto-generate from the registered route
        return route('hyperpay.callback.plain', ['mtid' => $merchantTransactionId]);
    }

    private function buildSignedCallbackUrl(Request $request, array $paymentData, string $merchantTransactionId): string
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

    private function extractPaymentFromCallback(Request $request): ?array
    {
        // Hyperpay sends payment result directly in callback for synchronous payments
        // Check if we have the necessary payment result parameters
        $resultCode = $request->get('result_code');
        $resultDescription = $request->get('result_description');
        $amount = $request->get('amount');
        $id = $request->get('id');

        if (! $resultCode || ! $resultDescription) {
            return null;
        }

        return [
            'result' => [
                'code' => $resultCode,
                'description' => $resultDescription,
            ],
            'amount' => $amount,
            'id' => $id,
            'merchantTransactionId' => $request->get('merchantTransactionId'),
        ];
    }

    private function buildCustomerData(): array
    {
        $user = auth()->user();
        $givenName = trim((string) ($user->first_name ?? ''));
        $surname = trim((string) ($user->last_name ?? ''));

        // Billing address — pulled from user profile
        // Defaults ensure Hyperpay's mandatory billing fields are never empty
        $address = trim((string) ($user->address ?? ''));
        $city    = trim((string) ($user->city    ?? ''));
        $country = strtoupper(trim((string) ($user->country ?? 'SA')));
        if (strlen($country) !== 2) {
            $country = 'SA';
        }

        return [
            'given_name'      => $givenName !== '' ? $givenName : 'Customer',
            'surname'         => $surname   !== '' ? $surname   : ($givenName !== '' ? $givenName : 'Customer'),
            'mobile'          => trim((string) ($user->mobile ?? '')),
            'email'           => trim((string) ($user->email  ?? '')),
            // Billing fields (mandatory for Hyperpay LIVE)
            'address'         => $address !== '' ? $address : 'N/A',
            'city'            => $city    !== '' ? $city    : 'Riyadh',
            'country'         => $country,
        ];
    }

    private function generateMerchantTransactionId(int $userId): string
    {
        // Hyperpay enforces a 16-character maximum for merchantTransactionId.
        return Str::upper(Str::random(16));
    }

    private function paymentCacheKey(string $merchantTransactionId): string
    {
        return 'hyperpay_payment_' . $merchantTransactionId;
    }
}