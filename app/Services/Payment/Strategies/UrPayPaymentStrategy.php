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
            } elseif ($checkout['type'] === 'html') {
                $responseData['payment_html'] = $checkout['html'];
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

        if ($checkout['type'] === 'html') {
            return response($checkout['html'], 200, [
                'Content-Type' => 'text/html; charset=ISO-8859-1',
            ]);
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

        if ($this->isHostedGatewayConfiguration($createOrderPath)) {
            return $this->createHostedCheckoutDocument($baseUrl, $createOrderPath, $amount, $currency, $invoiceRef, $merchantUrls);
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

    private function createHostedCheckoutDocument(
        string $baseUrl,
        string $createOrderPath,
        float $amount,
        string $currency,
        string $invoiceRef,
        array $merchantUrls
    ): array {
        $tranportalId = trim((string) config('urpay.username'));
        $tranportalPassword = trim((string) config('urpay.password'));
        $verifyOrderPath = trim((string) config('urpay.verify_order_path'), '/');

        if ($tranportalId === '' || $tranportalPassword === '') {
            throw new \RuntimeException('URPAY hosted payment requires Tranportal ID and Tranportal Password.');
        }

        if (! $this->isTranportalPath($verifyOrderPath)) {
            throw new \RuntimeException('URPAY hosted payment requires URPAY_VERIFY_ORDER_PATH to point to tranportal.htm.');
        }

        $mobileNumber = $this->normalizeUrPayMobileNumber((string) (auth()->user()->mobile ?? ''));
        if ($mobileNumber === null) {
            throw new \RuntimeException('تعذر بدء دفع URPAY لأن رقم جوال العميل غير صالح لصيغة URPAY المطلوبة. يجب إرسال رقم جوال مكوّن من 9 أرقام بدون 0 أو +966.');
        }

        $trackId = $this->buildNumericTrackId($invoiceRef);
        $merchantReference = $this->sanitizeUrPayAlphaNum($invoiceRef) ?: $trackId;

        $plainTrandata = [[
            'amt' => number_format($amount, 2, '.', ''),
            'action' => '1',
            'password' => $tranportalPassword,
            'id' => $tranportalId,
            'currencyCode' => $this->resolveCurrencyCode($currency),
            'trackId' => $trackId,
            'responseURL' => $merchantUrls['success'],
            'errorURL' => $merchantUrls['failure'],
            'udf1' => $merchantReference,
            'udf2' => $this->sanitizeUrPayAlphaNum((string) (auth()->id() ?? '')),
            'udf5' => $merchantReference,
            'langid' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'cust_mobile_number' => $mobileNumber,
            'cust_emailId' => (string) (auth()->user()->email ?? ''),
        ]];

        $endpointPath = $this->resolveTokenGenerationPath($createOrderPath);
        $requestBody = [[
            'id' => $tranportalId,
            'trandata' => $this->encryptTrandataPayload($plainTrandata),
            'responseURL' => $merchantUrls['success'],
            'errorURL' => $merchantUrls['failure'],
        ]];

        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-FORWARDED-FOR' => $this->buildForwardedForHeader(request()),
            ])
            ->post($baseUrl . '/' . ltrim($endpointPath, '/'), $requestBody);

        if (! $response->successful()) {
            throw new \RuntimeException('UrPay token generation failed: ' . $response->body());
        }

        if ($this->isInvalidAccessResponse($response->body())) {
            throw new \RuntimeException('URPAY gateway rejected the hosted payment request with InvalidAccess. Re-check Tranportal ID, Tranportal Password, Resource Key, Terminal configuration, and the merchant server IP allowlist with the bank.');
        }

        $responseData = $response->json();
        if (! is_array($responseData)) {
            throw new \RuntimeException('UrPay token generation returned a non-JSON response. Re-check the gateway endpoint paths and request format provided by the bank.');
        }

        if (is_array($responseData) && array_is_list($responseData)) {
            $responseData = $responseData[0] ?? [];
        }

        $status = (string) ($responseData['status'] ?? '');
        if ($status !== '1') {
            $message = trim((string) ($responseData['errorText'] ?? $responseData['result'] ?? ''));
            throw new \RuntimeException($message !== '' ? $message : 'UrPay token generation failed.');
        }

        $result = trim((string) ($responseData['result'] ?? ''));
        if ($result === '' || ! str_contains($result, ':')) {
            throw new \RuntimeException('UrPay token generation did not return PaymentID and payment page URL.');
        }

        [$paymentId, $paymentUrl] = explode(':', $result, 2);
        $paymentId = trim($paymentId);
        $paymentUrl = preg_replace('/\s+/', '', trim($paymentUrl));

        if ($paymentId === '' || $paymentUrl === '') {
            throw new \RuntimeException('UrPay token generation returned an invalid payment page response.');
        }

        $framedUrl = stripos($paymentUrl, 'paymentid=') !== false
            ? $paymentUrl
            : $paymentUrl . (str_contains($paymentUrl, '?') ? '&' : '?') . 'PaymentID=' . urlencode($paymentId);

        session()->put('urpay_payment.transaction_reference', $paymentId);
        session()->put('urpay_payment.track_id', $trackId);

        return [
            'type' => 'redirect',
            'url' => $framedUrl,
        ];
    }

    private function verifyPayment(Request $request, ?array $data): array
    {
        $callbackStatus = $this->resolveHostedCallbackStatus($request);
        $decryptedPayload = $this->getDecryptedResponsePayload($request);

        if ($callbackStatus !== null) {
            return [
                'ok' => true,
                'status' => $callbackStatus,
            ];
        }

        if ($decryptedPayload) {
            $message = trim((string) ($decryptedPayload['errorText'] ?? $decryptedPayload['result'] ?? ''));

            return [
                'ok' => false,
                'message' => $message !== '' ? $message : 'UrPay returned an unknown payment status.',
            ];
        }

        if ($request->filled('ErrorText') || $request->filled('errorText')) {
            return [
                'ok' => false,
                'message' => $this->resolveGatewayMessage($request, __('messages.payment_failed')),
            ];
        }

        if ($this->resolveCallbackReference($request, $data)) {
            return [
                'ok' => false,
                'message' => 'UrPay callback received without trandata result.',
            ];
        }

        return [
            'ok' => false,
            'message' => 'UrPay transaction reference is missing.',
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
            'paymentId',
            'paymentid',
            'checkout_id',
            'status',
            'payment_status',
            'order_id',
            'reference',
            'trandata',
            'transId',
            'transid',
            'result',
            'Result',
            'trackid',
            'trackId',
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

    private function resolveTokenGenerationPath(string $createOrderPath): string
    {
        $verifyOrderPath = trim((string) config('urpay.verify_order_path'), '/');

        if ($this->isHostedPagePath($createOrderPath) && $this->isTranportalPath($verifyOrderPath)) {
            return $verifyOrderPath;
        }

        return $createOrderPath;
    }

    private function isHostedGatewayConfiguration(string $createOrderPath): bool
    {
        return $this->isHostedPagePath($createOrderPath)
            || $this->isTranportalPath(trim((string) config('urpay.verify_order_path'), '/'));
    }

    private function buildForwardedForHeader(Request $request): string
    {
        $customerIp = trim((string) $request->ip());
        if ($customerIp === '') {
            $customerIp = '127.0.0.1';
        }

        $forwarded = trim((string) $request->header('X-Forwarded-For', ''));
        if ($forwarded === '') {
            return $customerIp;
        }

        return $customerIp . ',' . $forwarded;
    }

    private function encryptTrandataPayload(array $payload): string
    {
        $resourceKey = trim((string) config('urpay.token'));
        if ($resourceKey === '') {
            throw new \RuntimeException('URPAY resource key is missing.');
        }

        $plainJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($plainJson === false) {
            throw new \RuntimeException('Unable to encode UrPay trandata payload.');
        }

        $blockSize = openssl_cipher_iv_length('aes-256-cbc');
        $padded = $this->pkcs5Pad($plainJson, $blockSize);
        $encrypted = openssl_encrypt(
            $padded,
            'aes-256-cbc',
            $resourceKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            'PGKEYENCDECIVSPC'
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Unable to encrypt UrPay trandata.');
        }

        return urlencode(bin2hex($encrypted));
    }

    private function decryptTrandataPayload(string $encryptedHex): ?array
    {
        $resourceKey = trim((string) config('urpay.token'));
        if ($resourceKey === '') {
            return null;
        }

        $hex = trim(urldecode($encryptedHex));
        if ($hex === '') {
            return null;
        }

        $binary = @hex2bin($hex);
        if ($binary === false) {
            return null;
        }

        $decrypted = openssl_decrypt(
            $binary,
            'aes-256-cbc',
            $resourceKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            'PGKEYENCDECIVSPC'
        );

        if ($decrypted === false) {
            return null;
        }

        $unpadded = $this->pkcs5Unpad($decrypted);
        if ($unpadded === null) {
            return null;
        }

        $payload = json_decode($unpadded, true);

        if (! is_array($payload)) {
            $payload = json_decode(urldecode($unpadded), true);
        }

        if (! is_array($payload)) {
            return null;
        }

        if (array_is_list($payload)) {
            $payload = $payload[0] ?? null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function getDecryptedResponsePayload(Request $request): ?array
    {
        if ($request->attributes->has('urpay_decrypted_payload')) {
            return $request->attributes->get('urpay_decrypted_payload');
        }

        $encrypted = trim((string) $request->input('trandata', ''));
        if ($encrypted === '') {
            $request->attributes->set('urpay_decrypted_payload', null);
            return null;
        }

        $payload = $this->decryptTrandataPayload($encrypted);
        $request->attributes->set('urpay_decrypted_payload', $payload);

        return $payload;
    }

    private function pkcs5Pad(string $text, int $blockSize): string
    {
        $pad = $blockSize - (strlen($text) % $blockSize);
        return $text . str_repeat(chr($pad), $pad);
    }

    private function pkcs5Unpad(string $text): ?string
    {
        $length = strlen($text);
        if ($length === 0) {
            return null;
        }

        $pad = ord($text[$length - 1]);
        if ($pad < 1 || $pad > 16) {
            return null;
        }

        if (substr($text, -1 * $pad) !== str_repeat(chr($pad), $pad)) {
            return null;
        }

        return substr($text, 0, $length - $pad);
    }

    private function normalizeUrPayMobileNumber(string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^5\d{8}$/', $digits) === 1 ? $digits : null;
    }

    private function buildNumericTrackId(string $invoiceRef): string
    {
        $digits = preg_replace('/\D+/', '', $invoiceRef);
        $seed = now()->format('YmdHisv') . random_int(100, 999);

        if (is_string($digits) && $digits !== '') {
            return substr($seed . $digits, 0, 18);
        }

        return substr($seed, 0, 18);
    }

    private function sanitizeUrPayAlphaNum(string $value): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9]/', '', $value), 0, 30);
    }

    private function isInvalidAccessResponse(string $html): bool
    {
        $normalized = strtolower($html);

        return str_contains($normalized, 'invalidaccess.htm')
            || str_contains($normalized, 'removeexceptionsession')
            || str_contains($normalized, 'forwardtologout()');
    }

    private function resolveHostedCallbackStatus(Request $request): ?string
    {
        $payload = $this->getDecryptedResponsePayload($request);
        if ($payload) {
            $result = strtolower(trim((string) ($payload['result'] ?? '')));
            if ($result !== '') {
                if ($this->containsAny($result, ['captured', 'approved', 'authorized', 'authorised', 'paid', 'success'])) {
                    return 'success';
                }

                if ($this->containsAny($result, ['cancel', 'cancelled', 'canceled', 'abort'])) {
                    return 'cancel';
                }

                if ($this->containsAny($result, ['fail', 'declin', 'denied', 'reject', 'error'])) {
                    return 'failure';
                }
            }
        }

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
        $payload = $this->getDecryptedResponsePayload($request);
        $candidates = [
            $payload['trackId'] ?? null,
            $payload['paymentId'] ?? null,
            $payload['transId'] ?? null,
            $request->get('transaction_id'),
            $request->get('payment_id'),
            $request->get('checkout_id'),
            $request->get('order_id'),
            $request->get('reference'),
            $request->get('invoice'),
            $request->get('trackid'),
            $request->get('track_id'),
            $request->get('tranid'),
            $request->get('paymentId'),
            $request->get('paymentid'),
            $request->get('payid'),
            $request->get('udf1'),
            $data['transaction_reference'] ?? null,
            $data['track_id'] ?? null,
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
        $payload = $this->getDecryptedResponsePayload($request);
        $candidates = [
            $payload['errorText'] ?? null,
            $payload['result'] ?? null,
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

        if (str_contains($message, 'UrPay checkout failed:') || str_contains($message, 'UrPay token generation failed:')) {
            return 'فشل بدء عملية دفع URPAY من البوابة. يرجى مراجعة مسار إنشاء الـ token وبيانات الربط المرسلة من البنك.';
        }

        if (str_contains($message, 'URPAY hosted payment requires Tranportal ID and Tranportal Password.')) {
            return 'تعذر بدء دفع URPAY لأن بيانات Tranportal ID أو Tranportal Password غير مكتملة في إعدادات البيئة.';
        }

        if (str_contains($message, 'URPAY resource key is missing.')) {
            return 'تعذر بدء دفع URPAY لأن Resource Key غير موجودة في الإعدادات.';
        }

        if (str_contains($message, 'URPAY hosted payment requires URPAY_VERIFY_ORDER_PATH to point to tranportal.htm.')) {
            return 'تعذر بدء دفع URPAY لأن قيمة URPAY_VERIFY_ORDER_PATH يجب أن تشير إلى tranportal.htm الخاص بالبنك.';
        }

        if (str_contains($message, 'URPAY gateway rejected the hosted payment request with InvalidAccess.')) {
            return 'بوابة URPAY رفضت طلب الدفع برسالة InvalidAccess. غالبًا السبب في Tranportal ID أو Tranportal Password أو Resource Key أو إعدادات Terminal أو سماح عنوان IP من جهة البنك.';
        }

        if (str_contains($message, 'UrPay token generation returned a non-JSON response.')) {
            return 'بوابة URPAY أعادت ردًا غير متوقع بدل JSON. راجع قيم URPAY_CREATE_ORDER_PATH و URPAY_VERIFY_ORDER_PATH وتأكد أنها مطابقة لمسارات البنك.';
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
