<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooLoyaltyService
{
    /**
     * Get the live loyalty balance for a customer from Odoo.
     *
     * POST /odoo/loyalty/balance
     *
     * @return array{success: bool, points: float, found: bool, partner_id: int|null, partner_name: string|null, program: string|null, card_id: int|null}
     */
    public function getBalance(string $phone): array
    {
        $url = $this->buildUrl('/odoo/loyalty/balance');

        if ($url === null) {
            return $this->failedResponse('Odoo base URL not configured.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders($this->buildHeaders())
                ->post($url, ['data' => ['phone' => $phone]]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success'      => true,
                    'found'        => (bool) ($body['found'] ?? false),
                    'points'       => (float) ($body['points'] ?? 0),
                    'partner_id'   => $body['partner_id'] ?? null,
                    'partner_name' => $body['partner_name'] ?? null,
                    'program'      => $body['program'] ?? null,
                    'card_id'      => $body['card_id'] ?? null,
                ];
            }

            Log::warning('Odoo loyalty balance request failed.', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return $this->failedResponse('Odoo returned HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::error('Odoo loyalty balance exception.', [
                'phone'   => $phone,
                'message' => $e->getMessage(),
            ]);

            return $this->failedResponse($e->getMessage());
        }
    }

    /**
     * Add, deduct, or set loyalty points in Odoo.
     *
     * POST /odoo/loyalty/adjust
     *
     * @param string $phone     Customer phone number
     * @param string $operation "add" | "deduct" | "set"
     * @param float  $points    Number of points
     * @param string $reference Unique idempotency key (required)
     * @param string|null $note Optional note
     * @param string|null $name Optional customer name (used if customer must be created)
     *
     * @return array{success: bool, duplicate: bool, balance_before: float|null, balance_after: float|null, error: string|null, status_code: int|null}
     */
    public function adjust(string $phone, string $operation, float $points, string $reference, ?string $note = null, ?string $name = null): array
    {
        $url = $this->buildUrl('/odoo/loyalty/adjust');

        if ($url === null) {
            return $this->failedAdjustResponse('Odoo base URL not configured.');
        }

        $data = [
            'phone'     => $phone,
            'operation' => $operation,
            'points'    => $points,
            'reference' => $reference,
        ];

        if ($note !== null && $note !== '') {
            $data['note'] = $note;
        }

        if ($name !== null && $name !== '') {
            $data['name'] = $name;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders($this->buildHeaders())
                ->post($url, ['data' => $data]);

            $body = $response->json() ?? [];

            if ($response->successful()) {
                Log::info('Odoo loyalty adjust request successful.', [
                    'phone'          => $phone,
                    'operation'      => $operation,
                    'points'         => $points,
                    'reference'      => $reference,
                    'duplicate'      => (bool) ($body['duplicate'] ?? false),
                    'balance_before' => isset($body['balance_before']) ? (float) $body['balance_before'] : null,
                    'balance_after'  => isset($body['balance_after']) ? (float) $body['balance_after'] : null,
                ]);

                return [
                    'success'        => true,
                    'duplicate'      => (bool) ($body['duplicate'] ?? false),
                    'balance_before' => isset($body['balance_before']) ? (float) $body['balance_before'] : null,
                    'balance_after'  => isset($body['balance_after']) ? (float) $body['balance_after'] : null,
                    'error'          => null,
                    'status_code'    => $response->status(),
                ];
            }

            // 409 = insufficient points
            if ($response->status() === 409) {
                return [
                    'success'        => false,
                    'duplicate'      => false,
                    'balance_before' => isset($body['points']) ? (float) $body['points'] : null,
                    'balance_after'  => null,
                    'error'          => $body['message'] ?? 'Insufficient points',
                    'status_code'    => 409,
                ];
            }

            Log::warning('Odoo loyalty adjust request failed.', [
                'phone'     => $phone,
                'operation' => $operation,
                'points'    => $points,
                'reference' => $reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);

            return $this->failedAdjustResponse(
                $body['message'] ?? ('Odoo returned HTTP ' . $response->status()),
                $response->status()
            );
        } catch (\Throwable $e) {
            Log::error('Odoo loyalty adjust exception.', [
                'phone'     => $phone,
                'operation' => $operation,
                'points'    => $points,
                'reference' => $reference,
                'message'   => $e->getMessage(),
            ]);

            return $this->failedAdjustResponse($e->getMessage());
        }
    }

    /**
     * Get loyalty operation history for a customer from Odoo.
     *
     * POST /odoo/loyalty/history
     *
     * @return array{success: bool, records: array}
     */
    public function history(string $phone, int $limit = 20): array
    {
        $url = $this->buildUrl('/odoo/loyalty/history');

        if ($url === null) {
            return ['success' => false, 'records' => [], 'error' => 'Odoo base URL not configured.'];
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders($this->buildHeaders())
                ->post($url, ['data' => ['phone' => $phone, 'limit' => $limit]]);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'success' => true,
                    'records' => is_array($body) ? ($body['records'] ?? $body['data'] ?? $body) : [],
                ];
            }

            Log::warning('Odoo loyalty history request failed.', [
                'phone'  => $phone,
                'status' => $response->status(),
            ]);

            return ['success' => false, 'records' => [], 'error' => 'Odoo returned HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            Log::error('Odoo loyalty history exception.', [
                'phone'   => $phone,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'records' => [], 'error' => $e->getMessage()];
        }
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function buildHeaders(): array
    {
        $apiKey = (string) config('services.odoo.api_key');

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($apiKey !== '') {
            $headers['api-key'] = $apiKey;
        } else {
            // Fallback to legacy db/login/password auth
            $headers['db']       = (string) config('services.odoo.db');
            $headers['login']    = (string) config('services.odoo.login');
            $headers['password'] = (string) config('services.odoo.password');
        }

        return $headers;
    }

    private function buildUrl(string $path): ?string
    {
        $baseUrl = (string) config('services.odoo.base_url');

        if ($baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function timeout(): int
    {
        return (int) config('services.odoo.timeout', 15);
    }

    private function failedResponse(string $error): array
    {
        return [
            'success'      => false,
            'found'        => false,
            'points'       => 0,
            'partner_id'   => null,
            'partner_name' => null,
            'program'      => null,
            'card_id'      => null,
            'error'        => $error,
        ];
    }

    private function failedAdjustResponse(string $error, ?int $statusCode = null): array
    {
        return [
            'success'        => false,
            'duplicate'      => false,
            'balance_before' => null,
            'balance_after'  => null,
            'error'          => $error,
            'status_code'    => $statusCode,
        ];
    }
}
