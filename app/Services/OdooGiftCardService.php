<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooGiftCardService
{
    public function check(string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            return $this->failedResult($code, "Missing 'code' in data.", 400);
        }

        $url = $this->resolveCheckUrl();
        if ($url === '') {
            Log::warning('Odoo gift card check skipped: missing check URL.');

            return $this->failedResult($code, 'Odoo gift card validation is not configured.', 500);
        }

        $authHeaders = $this->authHeaders();
        if (empty($authHeaders)) {
            Log::warning('Odoo gift card check skipped: missing authentication.');

            return $this->failedResult($code, 'Odoo gift card authentication is not configured.', 500);
        }

        try {
            $response = Http::timeout((int) config('services.odoo.timeout', 15))
                ->acceptJson()
                ->withHeaders(array_merge([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ], $authHeaders))
                ->post($url, [
                    'data' => [
                        'code' => $code,
                    ],
                ]);

            return $this->normalizeResponse($response, $code);
        } catch (\Throwable $exception) {
            Log::error('Odoo gift card check exception.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failedResult($code, 'Unable to validate gift card right now.', 502);
        }
    }

    public function authHeaders(): array
    {
        $apiKey = trim((string) config('services.odoo.api_key'));
        if ($apiKey !== '') {
            return [
                'api-key' => $apiKey,
            ];
        }

        $db = trim((string) config('services.odoo.db'));
        $login = trim((string) config('services.odoo.login'));
        $password = trim((string) config('services.odoo.password'));

        if ($db === '' || $login === '' || $password === '') {
            return [];
        }

        return [
            'db' => $db,
            'login' => $login,
            'password' => $password,
        ];
    }

    private function resolveCheckUrl(): string
    {
        $url = trim((string) config('services.odoo.gift_card_check_url'));
        if ($url !== '') {
            return $url;
        }

        $bookingCreateUrl = trim((string) config('services.odoo.booking_create_url'));
        foreach (['/odoo/order/create', '/odoo/booking/create'] as $createPath) {
            if (str_contains($bookingCreateUrl, $createPath)) {
                return str_replace($createPath, '/odoo/giftcard/check', $bookingCreateUrl);
            }
        }

        return '';
    }

    private function normalizeResponse(Response $response, string $code): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $valid = $response->successful() && (bool) ($body['valid'] ?? false);

        return [
            'status' => $body['status'] ?? ($valid ? 'success' : 'failed'),
            'valid' => $valid,
            'code' => (string) ($body['code'] ?? $code),
            'balance' => (float) ($body['balance'] ?? 0),
            'expiration_date' => $body['expiration_date'] ?? null,
            'expired' => (bool) ($body['expired'] ?? false),
            'partner' => $body['partner'] ?? false,
            'message' => $body['message'] ?? ($valid ? __('messagess.gift_code_valid') : __('messagess.invalid_gift_code')),
            'status_code' => $response->status(),
            'raw' => $body,
        ];
    }

    private function failedResult(string $code, string $message, int $statusCode): array
    {
        return [
            'status' => 'failed',
            'valid' => false,
            'code' => $code,
            'balance' => 0.0,
            'expiration_date' => null,
            'expired' => false,
            'partner' => false,
            'message' => $message,
            'status_code' => $statusCode,
            'raw' => [],
        ];
    }
}
