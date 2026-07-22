<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HyperpayService
{
    private const DEFAULT_BASE_URL = 'https://eu-prod.oppwa.com';

    private string $baseUrl;
    private ?string $entityId;
    private ?string $entityIdMada;
    private ?string $authorizationToken;
    private ?string $fallbackAuthorizationToken;

    public function __construct()
    {
        $this->baseUrl = $this->resolveBaseUrl();
        [$this->entityId, $this->authorizationToken, $this->fallbackAuthorizationToken] = $this->resolveCredentials();
        $this->entityIdMada = $this->normalizeConfigValue(config('services.hyperpay.entity_id_mada')) ?: null;
    }

    public function createCheckout(float $amount, string $merchantTransactionId, string $shopperResultUrl, array $customer = [], string $brand = 'VISA'): array
    {
        // DIAGNOSTIC — remove after fix confirmed
        Log::info('Hyperpay createCheckout CALLED', [
            'amount'   => $amount,
            'brand'    => $brand,
            'base_url' => $this->baseUrl,
            'entity_id'=> $this->entityId,
            'customer_keys' => array_keys($customer),
        ]);

        $this->guardCredentials();

        // Select entity ID: MADA uses its own entity ID if configured
        $isMada = strtoupper($brand) === 'MADA';
        $entityId = ($isMada && $this->entityIdMada) ? $this->entityIdMada : $this->entityId;

        // Sanitize givenName — VISA-ACP processor requires Latin characters only.
        // Arabic / non-Latin chars cause 200.300.404 "invalid or missing parameter".
        $givenName = preg_replace('/[^a-zA-Z\s\'\-]/', '', (string) ($customer['given_name'] ?? ''));
        $givenName = trim(preg_replace('/\s+/', ' ', $givenName));
        if (mb_strlen($givenName) < 2) {
            $givenName = 'Customer';
        }

        // Sanitize surname — same Latin-only rule
        $surname = preg_replace('/[^a-zA-Z\s\'\-]/', '', (string) ($customer['surname'] ?? ''));
        $surname = trim(preg_replace('/\s+/', ' ', $surname));
        if (mb_strlen($surname) < 2) {
            // Fall back to givenName if surname is missing, not just 'Customer'
            $surname = mb_strlen($givenName) >= 2 ? $givenName : 'Customer';
        }

        // Validate email format
        $email = trim((string) ($customer['email'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }

        // Sanitize mobile format (numeric only, min 7 digits)
        $mobile = preg_replace('/[^0-9]/', '', (string) ($customer['mobile'] ?? ''));
        if (strlen($mobile) < 7) {
            $mobile = null;
        }

        // Mandatory billing fields — Hyperpay LIVE rejects without these
        $billingStreet  = trim((string) ($customer['billing_street'] ?? $customer['address'] ?? 'Main Street 3252'));
        $billingCity    = trim((string) ($customer['billing_city'] ?? $customer['city'] ?? 'Riyadh'));
        $billingState   = trim((string) ($customer['billing_state'] ?? $customer['city'] ?? 'Riyadh'));
        $billingCountry = strtoupper(trim((string) ($customer['billing_country'] ?? $customer['country'] ?? 'SA')));
        $billingPostcode= trim((string) ($customer['billing_postcode'] ?? '25262'));

        // Ensure country is 2-letter ISO alpha-2
        if (strlen($billingCountry) !== 2) {
            $billingCountry = 'SA';
        }

        $payload = array_filter([
            'entityId'                => $entityId,
            'amount'                  => number_format($amount, 2, '.', ''),
            'currency'                => 'SAR',
            'paymentType'             => 'DB',
            'merchantTransactionId'   => $merchantTransactionId,
            'customer.givenName'      => $givenName,
            'customer.surname'        => $surname,
            'customer.mobile'         => $mobile,
            'customer.email'          => $email,
            // Mandatory billing fields for LIVE
            'billing.street1'         => $billingStreet ?: 'N/A',
            'billing.city'            => $billingCity   ?: 'Riyadh',
            'billing.state'           => $billingState  ?: 'Riyadh',
            'billing.country'         => $billingCountry,
            'billing.postcode'        => $billingPostcode ?: '00000',
            // Ensure proper payment completion behavior
            'createRegistration'      => false,
        ], static fn ($value) => $value !== null && $value !== '');

        $response = $this->sendAuthorizedRequest(function (string $authorizationToken) use ($payload) {
            // DIAGNOSTIC — logs payload sent to Hyperpay
            Log::info('Hyperpay payload being sent', $payload);

            return Http::asForm()
                ->withToken($authorizationToken)
                ->acceptJson()
                ->post($this->baseUrl . '/v1/checkouts', $payload);
        });

        // DIAGNOSTIC — logs raw Hyperpay response always
        Log::info('Hyperpay raw response', [
            'status'       => $response->status(),
            'body'         => $response->body(),
        ]);

        $this->logGatewayResponse('create_checkout', $response, [
            'merchant_transaction_id' => $merchantTransactionId,
            'brand'                   => $brand,
            'entity_id_used'          => $entityId,
        ]);

        $decoded = $this->decodeResponse($response);

        // DIAGNOSTIC — check if response contains payment ID
        Log::info('Hyperpay checkout response structure', [
            'has_id' => isset($decoded['id']),
            'id' => $decoded['id'] ?? null,
            'has_payment_id' => isset($decoded['paymentId']),
            'payment_id' => $decoded['paymentId'] ?? null,
            'all_keys' => array_keys($decoded),
        ]);

        return $decoded;
    }

    public function fetchPaymentStatus(string $resourcePath, string $brand = 'VISA'): array
    {
        $this->guardCredentials();

        // Use the correct entity ID for status fetch too
        $isMada = strtoupper($brand) === 'MADA';
        $entityId = ($isMada && $this->entityIdMada) ? $this->entityIdMada : $this->entityId;

        // Build the URL using the resourcePath directly as provided by Hyperpay
        $url = str_starts_with($resourcePath, 'http')
            ? $resourcePath
            : $this->baseUrl . '/' . ltrim($resourcePath, '/');

        // Send only entityId as query parameter
        $response = $this->sendAuthorizedRequest(function (string $authorizationToken) use ($url, $entityId) {
            return Http::withToken($authorizationToken)
                ->acceptJson()
                ->get($url, [
                    'entityId' => $entityId,
                ]);
        });

        $this->logGatewayResponse('fetch_payment_status', $response, [
            'resource_path' => $resourcePath,
            'url' => $url,
        ]);

        // DIAGNOSTIC — log full raw response to see actual structure
        Log::info('Hyperpay fetchPaymentStatus raw response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $this->decodeResponse($response);
    }

    public function widgetScriptUrl(string $checkoutId, string $brand = 'VISA'): string
    {
        return $this->baseUrl . '/v1/paymentWidgets.js?checkoutId=' . urlencode($checkoutId);
    }

    public function brands(): array
    {
        return ['VISA', 'MASTER', 'MADA'];
    }

    public function isSuccessfulResult(?string $resultCode): bool
    {
        if (! $resultCode) {
            return false;
        }

        return preg_match('/^(000\.000\.|000\.100\.1|000\.[36]|000\.400\.[1][12]0)/', $resultCode) === 1;
    }

    public function isTestModeResult(?string $resultCode): bool
    {
        if (! $resultCode) {
            return false;
        }

        return in_array($resultCode, [
            '000.100.110',
            '000.100.111',
            '000.100.112',
        ], true);
    }

    public function testModeResultMessage(?string $resultCode): string
    {
        $suffix = $resultCode ? ' (' . $resultCode . ')' : '';

        return 'Payment gateway returned a TEST MODE success result' . $suffix . '. No amount was captured from the card. Please switch Hyperpay credentials/entity to LIVE mode.';
    }

    private function resolveCredentials(): array
    {
        $entityId = $this->normalizeConfigValue(config('services.hyperpay.entity_id'));
        $token = $this->normalizeConfigValue(config('services.hyperpay.token'));
        $rawKey = $this->normalizeConfigValue(config('services.hyperpay.raw_key'));
        $authorizationToken = $token;
        $fallbackAuthorizationToken = null;

        if (($entityId === '' || $authorizationToken === '') && $rawKey !== '') {
            $decoded = base64_decode($rawKey, true);
            $candidate = $decoded !== false ? $decoded : $rawKey;

            if (str_contains($candidate, '|')) {
                [$entityFromKey, $tokenFromKey] = array_pad(explode('|', $candidate, 2), 2, null);

                $entityId = $entityId !== '' ? $entityId : trim((string) $entityFromKey);
                if ($authorizationToken === '') {
                    $authorizationToken = trim((string) $tokenFromKey);
                }

                if ($authorizationToken !== $rawKey) {
                    $fallbackAuthorizationToken = $rawKey;
                }
            } elseif ($authorizationToken === '') {
                $authorizationToken = $rawKey;
            }
        }

        return [
            $entityId !== '' ? $entityId : null,
            $authorizationToken !== '' ? $authorizationToken : null,
            $fallbackAuthorizationToken !== '' ? $fallbackAuthorizationToken : null,
        ];
    }

    private function resolveBaseUrl(): string
    {
        $configured = $this->normalizeConfigValue(config('services.hyperpay.base_url'));
        $baseUrl = $configured !== '' ? $configured : self::DEFAULT_BASE_URL;

        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException(
                app()->getLocale() === 'ar'
                    ? 'رابط Hyperpay غير صالح. تأكد من HYPERPAY_BASE_URL في ملف .env.'
                    : 'Invalid Hyperpay base URL. Verify HYPERPAY_BASE_URL in the .env file.'
            );
        }

        return rtrim($baseUrl, '/');
    }

    private function guardCredentials(): void
    {
        if ($this->entityId && $this->authorizationToken) {
            return;
        }

        throw new \RuntimeException(
            app()->getLocale() === 'ar'
                ? 'بيانات Hyperpay غير مكتملة. تأكد من إعداد المفتاح أو بيانات الربط.'
                : 'Hyperpay credentials are incomplete.'
        );
    }

    private function decodeResponse(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException(
                app()->getLocale() === 'ar'
                    ? 'استجابة Hyperpay غير صالحة.'
                    : 'Invalid Hyperpay response.'
            );
        }

        if ($response->failed()) {
            if ($this->isInvalidAuthenticationFailure($data)) {
                throw new \RuntimeException(
                    app()->getLocale() === 'ar'
                        ? 'فشل توثيق Hyperpay. تأكد من مطابقة HYPERPAY_BASE_URL مع بيانات test/live الصحيحة، ومن صحة HYPERPAY_ENTITY_ID و HYPERPAY_TOKEN أو صيغة HYPERPAY_KEY.'
                        : 'Hyperpay authentication failed. Verify that HYPERPAY_BASE_URL matches the correct test/live credentials and that HYPERPAY_ENTITY_ID and HYPERPAY_TOKEN, or the HYPERPAY_KEY format, are valid.'
                );
            }

            if ($this->isInvalidOrMissingParameterFailure($data)) {
                throw new \RuntimeException(
                    app()->getLocale() === 'ar'
                        ? 'رفض Hyperpay طلب التحقق لوجود باراميتر ناقص أو غير صحيح. غالبًا السبب هو HYPERPAY_ENTITY_ID غير صحيح، أو HYPERPAY_BASE_URL لا يطابق بيئة الحساب، أو أن عملية الدفع أُنشئت ببيانات تختلف عن بيانات التحقق بعد الرجوع.'
                        : 'Hyperpay rejected the verification request because a parameter is missing or invalid. This usually means HYPERPAY_ENTITY_ID is incorrect, HYPERPAY_BASE_URL does not match the account environment, or the payment was created with credentials that differ from the ones used during callback verification.'
                );
            }

            throw new \RuntimeException(
                (string) data_get(
                    $data,
                    'result.description',
                    app()->getLocale() === 'ar' ? 'فشل الاتصال ببوابة Hyperpay.' : 'Hyperpay request failed.'
                )
            );
        }

        return $data;
    }

    private function isInvalidAuthenticationFailure(array $data): bool
    {
        $description = strtolower((string) data_get($data, 'result.description', ''));

        return str_contains($description, 'invalid authentication information');
    }

    private function isInvalidOrMissingParameterFailure(array $data): bool
    {
        $description = strtolower((string) data_get($data, 'result.description', ''));

        return str_contains($description, 'invalid or missing parameter');
    }

    private function normalizeConfigValue(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || $this->looksLikePlaceholder($value)) {
            return '';
        }

        return $value;
    }

    private function looksLikePlaceholder(string $value): bool
    {
        $upperValue = strtoupper($value);

        return str_starts_with($upperValue, 'YOUR_')
            || str_contains($upperValue, 'PLACEHOLDER');
    }

    private function logGatewayResponse(string $operation, Response $response, array $context = []): void
    {
        $payload = $response->json();
        $logContext = array_merge($context, [
            'operation'          => $operation,
            'base_url'           => $this->baseUrl,
            'entity_id_prefix'   => $this->entityId ? substr($this->entityId, 0, 8) : null,
            'status'             => $response->status(),
            'result_code'        => is_array($payload) ? data_get($payload, 'result.code') : null,
            'result_description' => is_array($payload) ? data_get($payload, 'result.description') : null,
            'checkout_id'        => is_array($payload) ? data_get($payload, 'id') : null,
            'ndc'                => is_array($payload) ? data_get($payload, 'ndc') : null,
        ]);

        if ($response->successful()) {
            Log::info('Hyperpay request succeeded', $logContext);
            return;
        }

        // Log full raw response body on failure to expose the exact offending parameter
        Log::warning('Hyperpay request failed', array_merge($logContext, [
            'raw_response' => $response->body(),
        ]));
    }

    private function sendAuthorizedRequest(callable $requestFactory): Response
    {
        $response = $requestFactory($this->authorizationToken);

        if (
            $response->failed()
            && $this->fallbackAuthorizationToken
            && $this->fallbackAuthorizationToken !== $this->authorizationToken
            && $this->shouldRetryWithFallbackToken($response)
        ) {
            Log::warning('Retrying Hyperpay request with fallback authorization token format', [
                'base_url' => $this->baseUrl,
                'entity_id_prefix' => $this->entityId ? substr($this->entityId, 0, 8) : null,
            ]);

            $response = $requestFactory($this->fallbackAuthorizationToken);
        }

        return $response;
    }

    private function shouldRetryWithFallbackToken(Response $response): bool
    {
        $data = $response->json();

        if (! is_array($data)) {
            return false;
        }

        return $this->isInvalidAuthenticationFailure($data)
            || $this->isInvalidOrMissingParameterFailure($data);
    }
}
