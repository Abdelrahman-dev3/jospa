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
     * Get the live balance from Odoo and synchronize the local user's balance if there is a discrepancy.
     * Creates a transaction record for any adjustments made.
     *
     * @param \App\Models\User $user
     * @return float|null The synchronized balance, or null if sync failed.
     */
    public function syncLocalBalance(\App\Models\User $user): ?float
    {
        $phone = $user->mobile ?? null;
        if (empty($phone)) {
            return null;
        }

        $result = $this->getBalance($phone);

        if ($result['success'] ?? false) {
            $odooPoints = (float) ($result['points'] ?? 0);
            
            $loyaltyModel = \App\Models\LoyaltyPoint::firstOrCreate(
                ['user_id' => $user->id],
                ['points' => 0]
            );
            $localPoints = (float) $loyaltyModel->points;

            if (round($localPoints, 2) !== round($odooPoints, 2)) {
                $difference = $odooPoints - $localPoints;
                $action = $difference > 0 ? 'add' : 'deduct';

                \App\Models\LoyaltyPointTransaction::create([
                    'user_id' => $user->id,
                    'action' => $action,
                    'points' => abs($difference),
                    'balance_after' => $odooPoints,
                    'source' => 'odoo_sync',
                    'meta' => ['reason' => 'Automatic sync to match Odoo'],
                ]);

                $loyaltyModel->points = $odooPoints;
                $loyaltyModel->save();

                Log::info('Loyalty points synced with Odoo automatically.', [
                    'user_id' => $user->id,
                    'old_balance' => $localPoints,
                    'new_balance' => $odooPoints,
                    'difference' => $difference,
                ]);
            }

            return $odooPoints;
        }

        return null;
    }

    /**
     * Fetch the gift card PDF voucher from Odoo.
     *
     * POST /odoo/giftcard/pdf
     *
     * @param string $code
     * @return string|null The base64 encoded PDF string, or null on failure.
     */
    public function fetchGiftCardPdf(string $code): ?string
    {
        $url = $this->buildUrl('/odoo/giftcard/pdf');

        if ($url === null) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout())
                ->withHeaders($this->buildHeaders())
                ->post($url, ['data' => ['code' => $code]]);

            if ($response->successful()) {
                $body = $response->json();
                
                $pdf = data_get($body, 'pdf') 
                    ?? data_get($body, 'result.pdf') 
                    ?? data_get($body, 'result.gift_card.pdf')
                    ?? data_get($body, 'data.pdf')
                    ?? data_get($body, 'result.data.pdf');
                    
                if (is_string($pdf) && $pdf !== '') {
                    return $pdf;
                }
            }
            
            Log::warning('Odoo gift card PDF fetch failed or missing pdf in response.', [
                'code' => $code,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Odoo gift card PDF fetch exception.', [
                'code' => $code,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
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
