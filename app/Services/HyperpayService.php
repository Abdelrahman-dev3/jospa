<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HyperpayService
{
    private const DEFAULT_BASE_URL = 'https://eu-test.oppwa.com';

    private string $baseUrl;
    private ?string $entityId;
    private ?string $authorizationToken;
    private ?string $fallbackAuthorizationToken;

    public function __construct()
    {
        $this->baseUrl = $this->resolveBaseUrl();
        [$this->entityId, $this->authorizationToken, $this->fallbackAuthorizationToken] = $this->resolveCredentials();
    }

    public function createCheckout(float $amount, string $merchantTransactionId, string $shopperResultUrl, array $customer = []): array
    {
        $this->guardCredentials();

        // Sanitize givenName and surname (must be valid strings, min 2 chars, fallback to 'Customer')
        $givenName = preg_replace('/[^a-zA-Z\p{Arabic}\s]/u', '', (string) ($customer['given_name'] ?? ''));
        $givenName = trim($givenName);
        if (mb_strlen($givenName) < 2) {
            $givenName = 'Customer';
        }

        $surname = preg_replace('/[^a-zA-Z\p{Arabic}\s]/u', '', (string) ($customer['surname'] ?? ''));
        $surname = trim($surname);
        if (mb_strlen($surname) < 2) {
            $surname = 'Customer';
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

        $payload = array_filter([
            'entityId' => $this->entityId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'SAR',
            'paymentType' => 'DB',
            'merchantTransactionId' => $merchantTransactionId,
            'shopperResultUrl' => $shopperResultUrl,
            'customer.givenName' => $givenName,
            'customer.surname' => $surname,
            'customer.mobile' => $mobile,
            'customer.email' => $email,
            // Exclude billing.country to avoid triggering strict billing address validation rules
        ], static fn ($value) => $value !== null && $value !== '');

        $response = $this->sendAuthorizedRequest(function (string $authorizationToken) use ($payload) {
            return Http::asForm()
                ->withToken($authorizationToken)
                ->acceptJson()
                ->post($this->baseUrl . '/v1/checkouts', $payload);
        });

        $this->logGatewayResponse('create_checkout', $response, [
            'merchant_transaction_id' => $merchantTransactionId,
        ]);

        return $this->decodeResponse($response);
    }

    public function fetchPaymentStatus(string $resourcePath, ?string $checkoutId = null): array
    {
        $this->guardCredentials();

        $normalizedPath = str_starts_with($resourcePath, 'http')
            ? $resourcePath
            : $this->baseUrl . '/' . ltrim($resourcePath, '/');

        $response = $this->sendAuthorizedRequest(function (string $authorizationToken) use ($normalizedPath) {
            return Http::withToken($authorizationToken)
                ->acceptJson()
                ->get($normalizedPath, [
                    'entityId' => $this->entityId,
                ]);
        });

        $responseData = $response->json();
        $resolvedCheckoutId = $checkoutId ?: $this->extractCheckoutIdFromResourcePath($resourcePath);

        $canonicalPath = $resolvedCheckoutId
            ? $this->baseUrl . '/v1/checkouts/' . rawurlencode($resolvedCheckoutId) . '/payment'
            : null;

        if (
            is_array($responseData)
            && $this->isInvalidOrMissingParameterFailure($responseData)
            && $resolvedCheckoutId
            && $canonicalPath
            && $canonicalPath !== $normalizedPath
        ) {
            Log::warning('Retrying Hyperpay payment status with canonical checkout path', [
                'resource_path' => $resourcePath,
                'normalized_path' => $normalizedPath,
                'canonical_path' => $canonicalPath,
                'checkout_id' => $resolvedCheckoutId,
                'entity_id_prefix' => $this->entityId ? substr($this->entityId, 0, 8) : null,
            ]);

            $response = $this->sendAuthorizedRequest(function (string $authorizationToken) use ($canonicalPath) {
                return Http::withToken($authorizationToken)
                    ->acceptJson()
                    ->get($canonicalPath, [
                        'entityId' => $this->entityId,
                    ]);
            });
        }

        $this->logGatewayResponse('fetch_payment_status', $response, [
            'resource_path' => $resourcePath,
            'normalized_path' => $normalizedPath,
            'checkout_id_hint' => $resolvedCheckoutId,
        ]);

        return $this->decodeResponse($response);
    }

    public function widgetScriptUrl(string $checkoutId): string
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
            'operation' => $operation,
            'base_url' => $this->baseUrl,
            'entity_id_prefix' => $this->entityId ? substr($this->entityId, 0, 8) : null,
            'status' => $response->status(),
            'result_code' => is_array($payload) ? data_get($payload, 'result.code') : null,
            'result_description' => is_array($payload) ? data_get($payload, 'result.description') : null,
            'checkout_id' => is_array($payload) ? data_get($payload, 'id') : null,
            'ndc' => is_array($payload) ? data_get($payload, 'ndc') : null,
        ]);

        if ($response->successful()) {
            Log::info('Hyperpay request succeeded', $logContext);

            return;
        }

        Log::warning('Hyperpay request failed', $logContext);
    }

    private function sendAuthorizedRequest(callable $requestFactory): Response
    {
        $response = $requestFactory($this->authorizationToken);

        if (
            ($response->failed() || $this->responseRequiresFallbackRetry($response))
            && $this->fallbackAuthorizationToken
            && $this->fallbackAuthorizationToken !== $this->authorizationToken
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

    private function responseRequiresFallbackRetry(Response $response): bool
    {
        return $this->shouldRetryWithFallbackToken($response);
    }

    private function extractCheckoutIdFromResourcePath(string $resourcePath): ?string
    {
        $path = parse_url($resourcePath, PHP_URL_PATH) ?: $resourcePath;

        if (preg_match('#/v1/checkouts/([^/]+)/payment#', $path, $matches) === 1) {
            return urldecode((string) $matches[1]);
        }

        return null;
    }
}
