<?php

namespace App\Services\Payment\Strategies;

use App\Models\PaymentAttempt;
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
            [, $data] = $this->createPaymentAttempt($request, $data, 'hyperpay', $remainingAmount);
            $hyperpay = app(HyperpayService::class);
            $merchantTransactionId = $this->generateMerchantTransactionId($data['user_id']);
            $shopperResultUrl = $this->buildShopperResultUrl($request, $data, $merchantTransactionId);
            $signedCallbackUrl = $this->buildSignedCallbackUrl($request, $data, $merchantTransactionId);
            // Detect selected brand from request to route to correct entity ID.
            $brand = $this->normalizeHyperpayBrand($request->get('brand', 'VISA'));
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

            $cachedPaymentData = array_merge($data, [
                'amount'                  => $remainingAmount,
                'subResult'               => $subResult,
                'checkout_id'             => $checkoutId,
                'merchant_transaction_id' => $merchantTransactionId,
                'callback_url'            => $signedCallbackUrl,
                'hyperpay_brand'          => $brand, // stored so callback uses the correct entity ID
            ]);

            Cache::put(
                $this->paymentCacheKey($merchantTransactionId),
                $cachedPaymentData,
                now()->addMinutes(self::CACHE_TTL_MINUTES)
            );

            $this->markPaymentAttemptPending($data['attempt_id'] ?? null, [
                'merchant_reference' => $merchantTransactionId,
                'gateway_checkout_id' => $checkoutId,
                'gateway_response' => $this->buildAttemptGatewayResponse($checkout, $cachedPaymentData),
            ]);

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
            $this->markPaymentAttemptFailed($data['attempt_id'] ?? null, $e->getMessage());
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
        $data = $this->resolvePaymentData($merchantTransactionId);

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
        $brand = $this->normalizeHyperpayBrand($data['hyperpay_brand'] ?? 'VISA');
        [$widgetBrands, $widgetLabels] = $this->widgetConfigurationForBrand($brand);

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
        \Log::info('Hyperpay callbackPlain parameters', $request->all());

        $resourcePath = (string) $request->get('resourcePath', '');
        $merchantTransactionId = (string) $request->get('mtid', '');
        $fallbackBrand = (string) $request->get('brand', '');

        if ($resourcePath === '') {
            return $this->respondFailure(
                $request,
                app()->getLocale() === 'ar'
                    ? 'تعذر التحقق من عملية الدفع.'
                    : 'Unable to verify payment.',
                400
            );
        }

        // Delegate to the core callback logic using the same mtid + resourcePath
        return $this->processPaymentVerification($request, $resourcePath, $merchantTransactionId, $fallbackBrand);
    }

    public function callback(Request $request)
    {
        if (! $request->hasValidSignatureWhileIgnoring(['id', 'resourcePath'])) {
            abort(403);
        }

        $resourcePath = (string) $request->get('resourcePath');
        $merchantTransactionId = (string) $request->get('mtid');
        $fallbackBrand = (string) $request->get('brand', '');

        if ($resourcePath === '') {
            return $this->respondFailure(
                $request,
                app()->getLocale() === 'ar'
                    ? 'تعذر التحقق من عملية الدفع.'
                    : 'Unable to verify payment.',
                400
            );
        }

        return $this->processPaymentVerification($request, $resourcePath, $merchantTransactionId, $fallbackBrand);
    }

    private function processPaymentVerification(Request $request, string $resourcePath, string $merchantTransactionId, string $fallbackBrand = ''): mixed
    {
        try {
            $hyperpay = app(HyperpayService::class);

            // Load cache first; fall back to payment_attempts if cache expired or was cleared.
            $cacheKey = $merchantTransactionId !== '' ? $this->paymentCacheKey($merchantTransactionId) : null;
            $data = $this->resolvePaymentData($merchantTransactionId);
            $brand = $this->normalizeHyperpayBrand($data['hyperpay_brand'] ?? $fallbackBrand ?? 'VISA');

            // Always verify payment status directly with Hyperpay API server-to-server
            $payment = $this->fetchPaymentStatusWithFallback($hyperpay, $resourcePath, $brand, $merchantTransactionId ?: 'unknown', $request->all());

            $resultCode = (string) data_get($payment, 'result.code', '');

            // Hyperpay may return the merchantTransactionId in the payment response
            $resolvedMtid = (string) (data_get($payment, 'merchantTransactionId') ?: $merchantTransactionId);
            if ($resolvedMtid !== '' && $resolvedMtid !== $merchantTransactionId) {
                $cacheKey = $this->paymentCacheKey($resolvedMtid);
                $data = $this->resolvePaymentData($resolvedMtid) ?? $data;
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
                $this->markPaymentAttemptFailed($data['attempt_id'] ?? null, (string) data_get(
                    $payment,
                    'result.description',
                    app()->getLocale() === 'ar' ? 'فشلت عملية الدفع.' : 'Payment failed.'
                ), [
                    'merchant_reference' => $merchantTransactionId,
                    'gateway_transaction_id' => data_get($payment, 'id'),
                    'gateway_checkout_id' => $data['checkout_id'] ?? null,
                    'gateway_response' => $payment,
                    'callback_payload' => $request->all(),
                ]);
                $this->forgetPaymentCache($cacheKey);

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

            if ($hyperpay->isTestModeResult($resultCode)) {
                $message = $hyperpay->testModeResultMessage($resultCode);

                \Log::warning('Hyperpay returned a test-mode payment result.', [
                    'merchant_reference' => $merchantTransactionId,
                    'attempt_id' => $data['attempt_id'] ?? null,
                    'checkout_id' => $data['checkout_id'] ?? null,
                    'gateway_transaction_id' => data_get($payment, 'id'),
                    'result_code' => $resultCode,
                    'result_description' => data_get($payment, 'result.description'),
                ]);

                $this->markPaymentAttemptFailed($data['attempt_id'] ?? null, $message, [
                    'merchant_reference' => $merchantTransactionId,
                    'gateway_transaction_id' => data_get($payment, 'id'),
                    'gateway_checkout_id' => $data['checkout_id'] ?? null,
                    'gateway_response' => $payment,
                    'callback_payload' => $request->all(),
                ]);
                $this->forgetPaymentCache($cacheKey);

                return $this->respondFailure($request, $message, 409);
            }

            $expectedAmount = (float) ($data['amount'] ?? 0);
            $paidAmount = (float) data_get($payment, 'amount', 0);

            if ($expectedAmount > 0 && $paidAmount > 0 && abs($expectedAmount - $paidAmount) > 0.01) {
                $this->markPaymentAttemptFailed($data['attempt_id'] ?? null, app()->getLocale() === 'ar'
                    ? 'قيمة الدفع لا تطابق المبلغ المتوقع.'
                    : 'Paid amount does not match expected amount.', [
                    'merchant_reference' => $merchantTransactionId,
                    'gateway_transaction_id' => data_get($payment, 'id'),
                    'gateway_checkout_id' => $data['checkout_id'] ?? null,
                    'gateway_response' => $payment,
                    'callback_payload' => $request->all(),
                ]);
                $this->forgetPaymentCache($cacheKey);

                return $this->respondFailure(
                    $request,
                    app()->getLocale() === 'ar'
                        ? 'قيمة الدفع لا تطابق المبلغ المتوقع.'
                        : 'Paid amount does not match expected amount.',
                    422
                );
            }

            if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                $this->markPaymentAttemptPaid($data['attempt_id'] ?? null, [
                    'merchant_reference' => $merchantTransactionId,
                    'gateway_transaction_id' => data_get($payment, 'id'),
                    'gateway_checkout_id' => $data['checkout_id'] ?? null,
                    'gateway_response' => $payment,
                    'callback_payload' => $request->all(),
                ]);
                $this->forgetPaymentCache($cacheKey);

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
                $data['subResult'] ?? [],
                [
                    'attempt_id' => $data['attempt_id'] ?? null,
                    'transaction_id' => (string) data_get($payment, 'id', ''),
                    'merchant_reference' => $merchantTransactionId,
                    'checkout_id' => $data['checkout_id'] ?? null,
                    'gateway_response' => $payment,
                    'callback_payload' => $request->all(),
                ]
            );

            $this->forgetPaymentCache($cacheKey);

            return $this->respondSuccess($request, 'Payment successful.', [
                'payment_id' => data_get($payment, 'id'),
                'checkout_id' => $data['checkout_id'] ?? null,
                'amount' => $paidAmount,
            ]);
        } catch (\Throwable $e) {
            $attemptId = isset($data) && is_array($data) ? ($data['attempt_id'] ?? null) : null;
            $this->markPaymentAttemptFailed($attemptId, $e->getMessage(), [
                'callback_payload' => $request->all(),
            ]);
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
        $brand = $this->normalizeHyperpayBrand($request->get('brand', $paymentData['hyperpay_brand'] ?? 'VISA'));
        $configured = rtrim((string) config('services.hyperpay.shopper_result_url', ''), '/');

        if ($configured !== '') {
            return $this->appendQueryParams($configured, [
                'mtid' => $merchantTransactionId,
                'brand' => $brand,
            ]);
        }

        // Fallback: auto-generate from the registered route
        return route('hyperpay.callback.plain', [
            'mtid' => $merchantTransactionId,
            'brand' => $brand,
        ]);
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
            'brand' => $this->normalizeHyperpayBrand($request->get('brand', $paymentData['hyperpay_brand'] ?? 'VISA')),
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

    private function buildAttemptGatewayResponse(array $checkout, array $paymentData): array
    {
        return [
            'checkout' => $checkout,
            'hyperpay_brand' => $paymentData['hyperpay_brand'] ?? 'VISA',
            'payment_context' => [
                'user_id' => $paymentData['user_id'] ?? null,
                'page' => $paymentData['page'] ?? 'cart',
                'payment_method' => $paymentData['payment_method'] ?? 'card',
                'couponCode' => $paymentData['couponCode'] ?? '',
                'submethods' => $paymentData['submethods'] ?? [],
                'final_before_sub' => $paymentData['final_before_sub'] ?? 0,
                'discountAmount' => $paymentData['discountAmount'] ?? 0,
                'couponDiscountAmount' => $paymentData['couponDiscountAmount'] ?? 0,
                'paymentGatewayDiscountAmount' => $paymentData['paymentGatewayDiscountAmount'] ?? 0,
                'paymentGatewayDiscountMethod' => $paymentData['paymentGatewayDiscountMethod'] ?? null,
                'paymentGatewayDiscountLabel' => $paymentData['paymentGatewayDiscountLabel'] ?? null,
                'tax' => $paymentData['tax'] ?? 0,
                'cart_ids' => $paymentData['cart_ids'] ?? [],
                'gift_ids' => $paymentData['gift_ids'] ?? [],
                'product_ids' => $paymentData['product_ids'] ?? [],
                'amount' => $paymentData['amount'] ?? 0,
                'subResult' => $paymentData['subResult'] ?? [],
                'checkout_id' => $paymentData['checkout_id'] ?? null,
                'merchant_transaction_id' => $paymentData['merchant_transaction_id'] ?? null,
                'callback_url' => $paymentData['callback_url'] ?? null,
                'hyperpay_brand' => $paymentData['hyperpay_brand'] ?? 'VISA',
                'attempt_id' => $paymentData['attempt_id'] ?? null,
            ],
        ];
    }

    private function resolvePaymentData(string $merchantTransactionId): ?array
    {
        if ($merchantTransactionId === '') {
            return null;
        }

        $cached = Cache::get($this->paymentCacheKey($merchantTransactionId));
        if (is_array($cached)) {
            return $cached;
        }

        $attempt = PaymentAttempt::where('gateway', 'hyperpay')
            ->where('merchant_reference', $merchantTransactionId)
            ->latest('id')
            ->first();

        return $attempt ? $this->restorePaymentDataFromAttempt($attempt) : null;
    }

    private function restorePaymentDataFromAttempt(PaymentAttempt $attempt): array
    {
        $gatewayResponse = is_array($attempt->gateway_response) ? $attempt->gateway_response : [];
        $context = data_get($gatewayResponse, 'payment_context', []);
        $context = is_array($context) ? $context : [];
        $submethods = $context['submethods'] ?? [
            'wallet' => (bool) $attempt->wallet_used,
            'loyalty' => (bool) $attempt->loyalty_used,
            'gift_code' => $attempt->gift_code,
        ];
        $submethods = is_array($submethods) ? $submethods : [];

        $subResult = $context['subResult'] ?? [
            'remaining_amount' => (float) $attempt->amount,
            'used_wallet' => 0,
            'used_loyalty' => 0,
            'used_gift' => 0,
        ];
        $subResult = is_array($subResult) ? $subResult : [];

        return [
            'user_id' => (int) ($context['user_id'] ?? $attempt->user_id),
            'page' => (string) ($context['page'] ?? $attempt->page ?? 'cart'),
            'payment_method' => (string) ($context['payment_method'] ?? $attempt->payment_method ?? 'card'),
            'couponCode' => (string) ($context['couponCode'] ?? $attempt->coupon_code ?? ''),
            'submethods' => [
                'wallet' => (bool) ($submethods['wallet'] ?? $attempt->wallet_used),
                'loyalty' => (bool) ($submethods['loyalty'] ?? $attempt->loyalty_used),
                'gift_code' => $submethods['gift_code'] ?? $attempt->gift_code,
            ],
            'final_before_sub' => (float) ($context['final_before_sub'] ?? $attempt->amount),
            'discountAmount' => (float) ($context['discountAmount'] ?? $attempt->discount_amount),
            'couponDiscountAmount' => (float) ($context['couponDiscountAmount'] ?? 0),
            'paymentGatewayDiscountAmount' => (float) ($context['paymentGatewayDiscountAmount'] ?? 0),
            'paymentGatewayDiscountMethod' => $context['paymentGatewayDiscountMethod'] ?? null,
            'paymentGatewayDiscountLabel' => $context['paymentGatewayDiscountLabel'] ?? null,
            'tax' => (float) ($context['tax'] ?? 0),
            'cart_ids' => $this->normalizeIdArray($context['cart_ids'] ?? $attempt->cart_ids ?? []),
            'gift_ids' => $this->normalizeIdArray($context['gift_ids'] ?? $attempt->gift_ids ?? []),
            'product_ids' => $this->normalizeIdArray($context['product_ids'] ?? []),
            'amount' => (float) ($context['amount'] ?? $attempt->amount),
            'subResult' => $subResult,
            'checkout_id' => (string) ($context['checkout_id'] ?? $attempt->gateway_checkout_id ?? ''),
            'merchant_transaction_id' => (string) ($context['merchant_transaction_id'] ?? $attempt->merchant_reference ?? ''),
            'callback_url' => $context['callback_url'] ?? null,
            'hyperpay_brand' => $this->normalizeHyperpayBrand($context['hyperpay_brand'] ?? data_get($gatewayResponse, 'hyperpay_brand', 'VISA')),
            'attempt_id' => (int) $attempt->id,
        ];
    }

    private function normalizeIdArray(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : null;
        }, $ids)));
    }

    private function forgetPaymentCache(?string $cacheKey): void
    {
        if ($cacheKey) {
            Cache::forget($cacheKey);
        }
    }

    private function normalizeHyperpayBrand(mixed $brand): string
    {
        $brand = strtoupper(trim((string) $brand));

        return match ($brand) {
            'MADA' => 'MADA',
            'APPLEPAY', 'APPLE_PAY', 'APPLE' => 'APPLEPAY',
            'MASTER', 'MASTERCARD', 'VISA', '' => 'VISA',
            default => 'VISA',
        };
    }

    private function widgetConfigurationForBrand(string $brand): array
    {
        return match ($brand) {
            'MADA' => ['MADA', ['Mada']],
            'APPLEPAY' => ['APPLEPAY', ['Apple Pay']],
            default => ['VISA MASTER', ['Visa', 'Mastercard']],
        };
    }

    private function fetchPaymentStatusWithFallback(
        HyperpayService $hyperpay,
        string $resourcePath,
        string $brand,
        string $merchantTransactionId,
        array $callbackPayload = []
    ): array {
        try {
            return $hyperpay->fetchPaymentStatus($resourcePath, $brand);
        } catch (\RuntimeException $exception) {
            if (! $this->shouldRetryStatusFetchWithAlternateBrand($exception->getMessage())) {
                throw $exception;
            }

            $alternateBrand = match ($brand) {
                'MADA' => 'VISA',
                'APPLEPAY' => 'VISA',
                default => 'MADA',
            };

            \Log::warning('Retrying Hyperpay status fetch with alternate brand entity.', [
                'merchant_reference' => $merchantTransactionId,
                'resource_path' => $resourcePath,
                'primary_brand' => $brand,
                'alternate_brand' => $alternateBrand,
                'message' => $exception->getMessage(),
                'callback_payload' => $callbackPayload,
            ]);

            return $hyperpay->fetchPaymentStatus($resourcePath, $alternateBrand);
        }
    }

    private function shouldRetryStatusFetchWithAlternateBrand(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'invalid or missing parameter')
            || str_contains($message, 'verification request');
    }

    private function appendQueryParams(string $url, array $params): string
    {
        $filtered = array_filter($params, static function ($value) {
            return $value !== null && $value !== '';
        });

        if ($filtered === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($filtered);
    }
}
