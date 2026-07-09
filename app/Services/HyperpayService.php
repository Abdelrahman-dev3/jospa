<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HyperpayService
{
    private string $baseUrl;
    private ?string $entityId;
    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.hyperpay.base_url', 'https://eu-test.oppwa.com'), '/');
        [$this->entityId, $this->token] = $this->resolveCredentials();
    }

    public function createCheckout(float $amount, string $merchantTransactionId, string $shopperResultUrl, array $customer = []): array
    {
        $this->guardCredentials();

        $payload = array_filter([
            'entityId' => $this->entityId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'SAR',
            'paymentType' => 'DB',
            'merchantTransactionId' => $merchantTransactionId,
            'shopperResultUrl' => $shopperResultUrl,
            'customer.givenName' => $customer['given_name'] ?? null,
            'customer.surname' => $customer['surname'] ?? null,
            'customer.mobile' => $customer['mobile'] ?? null,
            'customer.email' => $customer['email'] ?? null,
            'billing.country' => $customer['country'] ?? 'SA',
        ], static fn ($value) => $value !== null && $value !== '');

        $response = Http::asForm()
            ->withToken($this->token)
            ->acceptJson()
            ->post($this->baseUrl . '/v1/checkouts', $payload);

        return $this->decodeResponse($response);
    }

    public function fetchPaymentStatus(string $resourcePath): array
    {
        $this->guardCredentials();

        $normalizedPath = str_starts_with($resourcePath, 'http')
            ? $resourcePath
            : $this->baseUrl . '/' . ltrim($resourcePath, '/');

        $response = Http::withToken($this->token)
            ->acceptJson()
            ->get($normalizedPath, [
                'entityId' => $this->entityId,
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
        $entityId = trim((string) config('services.hyperpay.entity_id'));
        $token = trim((string) config('services.hyperpay.token'));
        $rawKey = trim((string) config('services.hyperpay.raw_key'));

        if (($entityId === '' || $token === '') && $rawKey !== '') {
            $decoded = base64_decode($rawKey, true);
            $candidate = $decoded !== false ? $decoded : $rawKey;

            if (str_contains($candidate, '|')) {
                [$entityFromKey, $tokenFromKey] = array_pad(explode('|', $candidate, 2), 2, null);

                $entityId = $entityId !== '' ? $entityId : trim((string) $entityFromKey);
                $token = $token !== '' ? $token : trim((string) $tokenFromKey);
            }
        }

        return [
            $entityId !== '' ? $entityId : null,
            $token !== '' ? $token : null,
        ];
    }

    private function guardCredentials(): void
    {
        if ($this->entityId && $this->token) {
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
}
