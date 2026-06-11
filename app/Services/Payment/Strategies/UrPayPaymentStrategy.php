<?php

namespace App\Services\Payment\Strategies;

use App\Models\User;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class UrPayPaymentStrategy extends BasePaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $prepared = $this->preparePaymentFlow($request, $typePage, 'urpay');
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

        $merchantUrls = $this->buildSignedMerchantUrls($request, $typePage, $data);

        try {
            $checkout = $this->createCheckoutRequest($request, $remainingAmount, $data, $merchantUrls);
        } catch (\Throwable $e) {
            if (! $this->wantsJson($request)) {
                session()->forget('urpay_payment');
            }

            return $this->respondPayException($request, $e);
        }

        if ($this->wantsJson($request)) {
            $responseData = [
                'amount' => $remainingAmount,
                'payment_method' => $data['payment_method'],
                'discount_amount' => $data['discountAmount'] ?? 0,
            ];

            if ($checkout['type'] === 'redirect') {
                $responseData['payment_url'] = $checkout['url'];
            } else {
                $responseData['payment_action'] = $checkout['action'];
                $responseData['payment_method_http'] = $checkout['method'];
                $responseData['payment_fields'] = $checkout['fields'];
            }

            return response()->json([
                'status' => true,
                'message' => 'Redirect to payment gateway.',
                'data' => $responseData,
            ]);
        }

        if ($checkout['type'] === 'redirect') {
            return redirect()->away($checkout['url']);
        }

        return response()->view('frontend.payment-status.redirect-form', [
            'actionUrl' => $checkout['action'],
            'method' => $checkout['method'],
            'fields' => $checkout['fields'],
            'message' => app()->getLocale() === 'ar'
                ? 'جار تحويلك إلى بوابة الدفع، الرجاء الانتظار...'
                : 'Redirecting you to the payment gateway. Please wait...',
        ]);
    }

    public function success(Request $request)
    {
        return $this->handleCallback($request, 'success');
    }

    public function failure(Request $request)
    {
        session()->forget('urpay_payment');

        return $this->respondFailure(
            $request,
            $this->resolveGatewayMessage($request, __('messages.payment_failed')),
            402
        );
    }

    public function cancel(Request $request)
    {
        session()->forget('urpay_payment');

        return $this->respondFailure(
            $request,
            $this->resolveGatewayMessage($request, __('messages.payment_cancelled')),
            400
        );
    }

    private function handleCallback(Request $request, string $expectedOutcome)
    {
        $data = session('urpay_payment');

        if ($data && auth()->check() && $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        $verification = $this->verifyPayment($request, $data);
        if (! $verification['ok']) {
            session()->forget('urpay_payment');

            return $this->respondFailure($request, $verification['message'], 422);
        }

        if (($verification['status'] ?? 'success') !== $expectedOutcome) {
            session()->forget('urpay_payment');

            $failureMessage = $expectedOutcome === 'success'
                ? $this->resolveGatewayMessage($request, __('messages.payment_failed'))
                : __('messages.payment_failed');

            return $this->respondFailure($request, $failureMessage, 402);
        }

        if ($data) {
            if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                session()->forget('urpay_payment');

                return $this->respondSuccess($request, 'Payment already finalized.');
            }

            $subMethodService = app(PaymentSubMethodsService::class);
            $fakeRequest = new Request([
                'wallet' => $data['submethods']['wallet'] ?? false,
                'loyalty' => $data['submethods']['loyalty'] ?? false,
                'gift_code' => $data['submethods']['gift_code'] ?? null,
            ]);
            $subResult = $subMethodService->apply($data['user_id'], $fakeRequest, $data['final_before_sub']);
            if (isset($subResult['error'])) {
                session()->forget('urpay_payment');

                return $this->respondFailure($request, $subResult['error'], 422);
            }

            $invoiceId = $this->commitFinalizedPayment($data['user_id'], $fakeRequest, $data, $subResult);
            session()->forget('urpay_payment');

            return $this->respondSuccess($request, 'Payment captured successfully.', [
                'invoice_id' => $invoiceId ?? null,
            ]);
        }

        $context = $this->resolveStatelessContext($request);
        if (! $context) {
            return $this->respondFailure($request, 'Invalid payment callback.', 400);
        }

        $user = User::find($context['user_id']);
        if (! $user) {
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

        $invoiceId = $this->commitFinalizedPayment($user->id, $fakeRequest, [
            'final_before_sub' => $totalData['total'],
            'tax' => $totalData['tax'],
            'discountAmount' => $totalData['discountAmount'],
            'page' => $context['page'],
            'cart_ids' => $totalData['cart_ids'] ?? [],
            'gift_ids' => $totalData['gift_ids'] ?? [],
            'payment_method' => 'urpay',
            'couponCode' => $context['couponCode'] ?? '',
        ], $subResult);

        return $this->respondSuccess($request, 'Payment captured successfully.', [
            'invoice_id' => $invoiceId ?? null,
        ]);
    }

    private function createCheckoutRequest(Request $request, float $amount, array $data, array $merchantUrls): array
    {
        $user = auth()->user();
        $invoiceRef = $this->buildInvoiceReference($data);
        $buyerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->full_name ?? 'Customer');
        $currency = config('urpay.currency', 'SAR');

        session([
            'urpay_payment' => array_merge($data, [
                'amount' => $amount,
                'invoice_reference' => $invoiceRef,
                'transaction_reference' => $invoiceRef,
            ]),
        ]);

        $template = trim((string) config('urpay.checkout_url_template'));
        if ($template !== '' && $this->templateHasPlaceholders($template)) {
            return [
                'type' => 'redirect',
                'url' => $this->buildTemplateCheckoutUrl($template, [
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                    'reference' => $invoiceRef,
                    'invoice' => $invoiceRef,
                    'customer_name' => $buyerName,
                    'customer_phone' => $user->mobile ?? '',
                    'success_url' => $merchantUrls['success'],
                    'failure_url' => $merchantUrls['failure'],
                    'cancel_url' => $merchantUrls['cancel'],
                    'lang' => app()->getLocale() ?? 'en',
                ]),
            ];
        }

        $baseUrl = rtrim((string) config('urpay.base_url'), '/');
        $createOrderPath = trim((string) config('urpay.create_order_path'), '/');

        if ($baseUrl === '' || $createOrderPath === '') {
            throw new \RuntimeException('UrPay is not fully configured. Set URPAY_CHECKOUT_URL_TEMPLATE or the API endpoint settings first.');
        }

        if ($this->isHostedPagePath($createOrderPath)) {
            return $this->buildHostedCheckoutForm($baseUrl, $createOrderPath, $amount, $currency, $invoiceRef, $buyerName, $merchantUrls, $user);
        }

        $payload = [
            'merchant_id' => config('urpay.merchant_id'),
            'terminal_id' => config('urpay.terminal_id'),
            'amount' => round($amount, 2),
            'currency' => $currency,
            'order_reference' => $invoiceRef,
            'order_number' => $invoiceRef,
            'description' => "Invoice #{$invoiceRef}",
            'customer' => [
                'name' => $buyerName,
                'phone' => $user->mobile ?? '',
            ],
            'phone' => $user->mobile ?? '',
            'customer_name' => $buyerName,
            'customer_phone' => $user->mobile ?? '',
            'merchant_urls' => $merchantUrls,
            'lang' => app()->getLocale() ?? 'en',
            'platform' => $request->get('platform', 'web'),
        ];

        $http = Http::acceptJson()->asJson();
        $token = trim((string) config('urpay.token'));
        if ($token !== '') {
            $http = $http->withToken($token);
        }

        $username = trim((string) config('urpay.username'));
        $password = trim((string) config('urpay.password'));
        if ($username !== '' || $password !== '') {
            $http = $http->withBasicAuth($username, $password);
        }

        $response = $http->post($baseUrl . '/' . $createOrderPath, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('UrPay checkout failed: ' . $response->body());
        }

        $responseData = $response->json();
        $paymentUrl = $responseData['payment_url']
            ?? $responseData['redirect_url']
            ?? $responseData['checkout_url']
            ?? data_get($responseData, 'data.payment_url')
            ?? data_get($responseData, 'data.redirect_url')
            ?? data_get($responseData, 'data.checkout_url');

        if (! $paymentUrl) {
            throw new \RuntimeException('UrPay checkout URL not found in the gateway response.');
        }

        $reference = $responseData['transaction_id']
            ?? $responseData['payment_id']
            ?? $responseData['checkout_id']
            ?? $responseData['order_id']
            ?? data_get($responseData, 'data.transaction_id')
            ?? data_get($responseData, 'data.payment_id');

        if ($reference) {
            session()->put('urpay_payment.transaction_reference', $reference);
        }

        return [
            'type' => 'redirect',
            'url' => $paymentUrl,
        ];
    }

    private function buildHostedCheckoutForm(
        string $baseUrl,
        string $createOrderPath,
        float $amount,
        string $currency,
        string $invoiceRef,
        string $buyerName,
        array $merchantUrls,
        $user
    ): array {
        $tranportalId = trim((string) config('urpay.username'));
        $tranportalPassword = trim((string) config('urpay.password'));

        if ($tranportalId === '' || $tranportalPassword === '') {
            throw new \RuntimeException('URPAY hosted payment requires Tranportal ID and Tranportal Password.');
        }

        $actionUrl = $baseUrl . '/' . ltrim($createOrderPath, '/');
        $fields = [
            'id' => $tranportalId,
            'password' => $tranportalPassword,
            'action' => '1',
            'langid' => app()->getLocale() === 'ar' ? 'AR' : 'EN',
            'currencycode' => $this->resolveCurrencyCode($currency),
            'amt' => number_format($amount, 2, '.', ''),
            'trackid' => $invoiceRef,
            'responseURL' => $merchantUrls['success'],
            'errorURL' => $merchantUrls['failure'],
            'cancelURL' => $merchantUrls['cancel'],
            'udf1' => $invoiceRef,
            'udf2' => (string) ($user->id ?? ''),
            'udf3' => (string) ($user->mobile ?? ''),
            'udf4' => (string) config('urpay.merchant_id'),
            'udf5' => (string) config('urpay.terminal_id'),
            'merchantid' => (string) config('urpay.merchant_id'),
            'terminalid' => (string) config('urpay.terminal_id'),
            'customer_name' => $buyerName,
            'customer_phone' => (string) ($user->mobile ?? ''),
        ];

        return [
            'type' => 'form',
            'action' => $actionUrl,
            'method' => 'POST',
            'fields' => array_filter($fields, static function ($value) {
                return $value !== null && $value !== '';
            }),
        ];
    }

    private function verifyPayment(Request $request, ?array $data): array
    {
        $callbackStatus = $this->resolveHostedCallbackStatus($request);
        $baseUrl = rtrim((string) config('urpay.base_url'), '/');
        $verifyOrderPath = trim((string) config('urpay.verify_order_path'), '/');

        if ($callbackStatus !== null && ($verifyOrderPath === '' || $this->isHostedPagePath($verifyOrderPath) || $this->isTranportalPath($verifyOrderPath))) {
            return [
                'ok' => true,
                'status' => $callbackStatus,
            ];
        }

        if ($baseUrl === '' || $verifyOrderPath === '') {
            return $callbackStatus !== null
                ? ['ok' => true, 'status' => $callbackStatus]
                : [
                    'ok' => false,
                    'message' => 'UrPay callback received, but payment verification is not configured yet.',
                ];
        }

        $reference = $this->resolveCallbackReference($request, $data);
        if (! $reference) {
            return $callbackStatus !== null
                ? ['ok' => true, 'status' => $callbackStatus]
                : [
                    'ok' => false,
                    'message' => 'UrPay transaction reference is missing.',
                ];
        }

        $http = Http::acceptJson();
        $token = trim((string) config('urpay.token'));
        if ($token !== '') {
            $http = $http->withToken($token);
        }

        $username = trim((string) config('urpay.username'));
        $password = trim((string) config('urpay.password'));
        if ($username !== '' || $password !== '') {
            $http = $http->withBasicAuth($username, $password);
        }

        $payload = [
            'transaction_id' => $reference,
            'payment_id' => $reference,
            'checkout_id' => $reference,
            'order_id' => $reference,
            'reference' => $reference,
            'trackid' => $reference,
        ];

        $response = $http->get($baseUrl . '/' . $verifyOrderPath, $payload);

        if ($this->shouldRetryVerificationAsPost($response)) {
            $retryHttp = Http::acceptJson()->asForm();
            if ($token !== '') {
                $retryHttp = $retryHttp->withToken($token);
            }

            if ($username !== '' || $password !== '') {
                $retryHttp = $retryHttp->withBasicAuth($username, $password);
            }

            $response = $retryHttp->post($baseUrl . '/' . $verifyOrderPath, $payload);
        }

        if (! $response->successful()) {
            return $callbackStatus !== null
                ? ['ok' => true, 'status' => $callbackStatus]
                : [
                    'ok' => false,
                    'message' => 'Failed to verify UrPay payment.',
                ];
        }

        $body = $response->json();
        $status = strtolower((string) (
            $body['status']
            ?? $body['payment_status']
            ?? $body['result']
            ?? data_get($body, 'data.status')
            ?? data_get($body, 'data.payment_status')
            ?? data_get($body, 'data.result')
            ?? ''
        ));

        if (in_array($status, ['success', 'paid', 'captured', 'authorized', 'approved'], true)) {
            return [
                'ok' => true,
                'status' => 'success',
            ];
        }

        if (in_array($status, ['failed', 'failure', 'declined'], true)) {
            return [
                'ok' => true,
                'status' => 'failure',
            ];
        }

        if (in_array($status, ['cancel', 'cancelled', 'canceled'], true)) {
            return [
                'ok' => true,
                'status' => 'cancel',
            ];
        }

        return $callbackStatus !== null
            ? ['ok' => true, 'status' => $callbackStatus]
            : [
                'ok' => false,
                'message' => 'UrPay returned an unknown payment status.',
            ];
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

        $successRoute = $this->wantsJson($request) ? 'api.urpay.success' : 'urpay.success';
        $failureRoute = $this->wantsJson($request) ? 'api.urpay.failure' : 'urpay.failure';
        $cancelRoute = $this->wantsJson($request) ? 'api.urpay.cancel' : 'urpay.cancel';

        return [
            'success' => URL::temporarySignedRoute($successRoute, now()->addMinutes(30), $params),
            'failure' => URL::temporarySignedRoute($failureRoute, now()->addMinutes(30), $params),
            'cancel' => URL::temporarySignedRoute($cancelRoute, now()->addMinutes(30), $params),
        ];
    }

    private function resolveStatelessContext(Request $request): ?array
    {
        if (! $request->hasValidSignatureWhileIgnoring([
            'transaction_id',
            'payment_id',
            'checkout_id',
            'status',
            'payment_status',
            'order_id',
            'reference',
            'result',
            'Result',
            'trackid',
            'track_id',
            'tranid',
            'payid',
            'auth',
            'ref',
            'udf1',
            'udf2',
            'udf3',
            'udf4',
            'udf5',
            'ErrorText',
            'errorText',
            'responsecode',
        ])) {
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

    private function buildInvoiceReference(array $data): string
    {
        $ids = $data['cart_ids'] ?? [];
        if (empty($ids)) {
            $ids = $data['gift_ids'] ?? [];
        }

        return 'INV-' . (! empty($ids) ? implode('-', $ids) : now()->timestamp);
    }

    private function buildTemplateCheckoutUrl(string $template, array $values): string
    {
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{' . $key . '}'] = rawurlencode((string) $value);
        }

        return strtr($template, $replacements);
    }

    private function templateHasPlaceholders(string $template): bool
    {
        return preg_match('/\{[a-z0-9_]+\}/i', $template) === 1;
    }

    private function shouldRetryVerificationAsPost($response): bool
    {
        if ($response->status() === 405) {
            return true;
        }

        return str_contains(strtolower($response->body()), "request method 'get' not supported");
    }

    private function resolveCurrencyCode(string $currency): string
    {
        return match (strtoupper($currency)) {
            'SAR' => '682',
            'KWD' => '414',
            'BHD' => '048',
            'AED' => '784',
            'QAR' => '634',
            'OMR' => '512',
            default => strtoupper($currency),
        };
    }

    private function resolveHostedCallbackStatus(Request $request): ?string
    {
        $errorText = trim((string) ($request->input('ErrorText') ?? $request->input('errorText') ?? ''));
        if ($errorText !== '') {
            return 'failure';
        }

        $candidates = [
            $request->input('status'),
            $request->input('payment_status'),
            $request->input('result'),
            $request->input('Result'),
            $request->input('responseMessage'),
            $request->input('authRespMessage'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '') {
                continue;
            }

            if ($this->containsAny($normalized, ['captured', 'approved', 'authorized', 'authorised', 'paid', 'success'])) {
                return 'success';
            }

            if ($this->containsAny($normalized, ['cancel', 'cancelled', 'canceled', 'abort'])) {
                return 'cancel';
            }

            if ($this->containsAny($normalized, ['fail', 'declin', 'denied', 'reject', 'error'])) {
                return 'failure';
            }
        }

        return null;
    }

    private function resolveCallbackReference(Request $request, ?array $data): ?string
    {
        $candidates = [
            $request->get('transaction_id'),
            $request->get('payment_id'),
            $request->get('checkout_id'),
            $request->get('order_id'),
            $request->get('reference'),
            $request->get('invoice'),
            $request->get('trackid'),
            $request->get('track_id'),
            $request->get('tranid'),
            $request->get('payid'),
            $request->get('udf1'),
            $data['transaction_reference'] ?? null,
            $data['invoice_reference'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveGatewayMessage(Request $request, string $default): string
    {
        $candidates = [
            $request->input('ErrorText'),
            $request->input('errorText'),
            $request->input('responseMessage'),
            $request->input('authRespMessage'),
            $request->input('result'),
            $request->input('Result'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function presentableErrorMessage(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        if ($message === '') {
            return __('messages.payment_failed');
        }

        if (str_contains($message, 'UrPay checkout URL not found')) {
            return 'تعذر إنشاء رابط دفع URPAY. الاستجابة القادمة من البوابة لا تحتوي على رابط تحويل صالح، وغالبًا أن إعدادات الربط الحالية غير مطابقة لمتطلبات البنك.';
        }

        if (str_contains($message, 'UrPay checkout failed:')) {
            return 'فشل بدء عملية دفع URPAY من البوابة. يرجى مراجعة مسار الإنشاء وبيانات الربط المرسلة من البنك.';
        }

        if (str_contains($message, 'URPAY hosted payment requires Tranportal ID and Tranportal Password.')) {
            return 'تعذر بدء دفع URPAY لأن بيانات Tranportal ID أو Tranportal Password غير مكتملة في إعدادات البيئة.';
        }

        return $message;
    }

    private function isHostedPagePath(string $path): bool
    {
        return str_ends_with(strtolower($path), 'hosted.htm');
    }

    private function isTranportalPath(string $path): bool
    {
        return str_ends_with(strtolower($path), 'tranportal.htm');
    }
}
