<?php

namespace App\Services\PaymentRequest;

use App\Models\PaymentRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sends paid payment requests to Odoo.
 *
 * Same URL and credentials as the booking sync (services.odoo.booking_create_url),
 * with its own event: odoo_create_payment_invoice. Odoo answers success for a first
 * send and for a repeat (flag already_recorded) with the same Odoo invoice number,
 * so retries are safe. Disabled until services.odoo.payment_invoice_sync_enabled is true.
 */
class OdooPaymentInvoiceSyncService
{
    public const EVENT = 'odoo_create_payment_invoice';

    /** Give up automatic retries after this many attempts (cron every 5 minutes ≈ 1 hour). */
    public const MAX_ATTEMPTS = 12;

    /** A send stuck in "sending" (process died) is retried after this. */
    private const STALE_SENDING_MINUTES = 10;

    public static function enabled(): bool
    {
        return (bool) config('services.odoo.payment_invoice_sync_enabled', false);
    }

    /** Called when a request becomes paid: queue it, and try right after the response is sent. */
    public function queue(PaymentRequest $request): void
    {
        PaymentRequest::whereKey($request->id)
            ->whereNull('odoo_sync_status')
            ->update(['odoo_sync_status' => PaymentRequest::ODOO_PENDING]);

        if (! self::enabled()) {
            return;
        }

        $id = $request->id;
        dispatch(function () use ($id) {
            $pr = PaymentRequest::find($id);
            if ($pr) {
                app(OdooPaymentInvoiceSyncService::class)->sync($pr);
            }
        })->afterResponse();
    }

    /** Scheduled retry (payment-requests:sync-odoo). Returns how many were attempted. */
    public function syncDue(): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $due = PaymentRequest::query()
            ->where('status', PaymentRequest::STATUS_PAID)
            ->where('odoo_sync_attempts', '<', self::MAX_ATTEMPTS)
            ->where(function ($q) {
                $q->whereIn('odoo_sync_status', [PaymentRequest::ODOO_PENDING, PaymentRequest::ODOO_FAILED])
                    ->orWhere(function ($s) {
                        $s->where('odoo_sync_status', PaymentRequest::ODOO_SENDING)
                            ->where('odoo_last_attempt_at', '<', now()->subMinutes(self::STALE_SENDING_MINUTES));
                    });
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($due as $request) {
            $this->sync($request);
        }

        return $due->count();
    }

    /** Send one paid request. Returns true when Odoo confirmed it (new or already recorded). */
    public function sync(PaymentRequest $request, bool $manual = false): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $url = (string) config('services.odoo.booking_create_url');
        $db = (string) config('services.odoo.db');
        $login = (string) config('services.odoo.login');
        $password = (string) config('services.odoo.password');

        if ($url === '' || $db === '' || $login === '' || $password === '') {
            Log::warning('Odoo payment invoice sync skipped: missing Odoo URL or credentials.', ['payment_request' => $request->number]);

            return false;
        }

        if (! $this->claim($request, $manual)) {
            return false;
        }

        $request->refresh();
        $payload = ['event' => self::EVENT, 'data' => $this->buildPayload($request)];

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout((int) config('services.odoo.timeout', 15))
                ->acceptJson()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'db' => $db,
                    'login' => $login,
                    'password' => $password,
                ])
                ->withBody((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json')
                ->send('POST', $url);
        } catch (\Throwable $e) {
            return $this->fail($request, 'Connection error: '.$e->getMessage());
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $result = $this->resultData($body);

        if (! $response->successful() || $this->rejected($body, $result)) {
            return $this->fail($request, $this->errorMessage($response, $body, $result));
        }

        $odooNumber = $this->invoiceNumber($result);
        $pdf = $this->storePdf($request, $this->invoiceDocument($result));

        $request->update([
            'odoo_sync_status' => PaymentRequest::ODOO_SYNCED,
            'odoo_invoice_number' => $odooNumber ?? $request->odoo_invoice_number,
            'odoo_invoice_pdf' => $pdf ?? $request->odoo_invoice_pdf,
            'odoo_last_error' => null,
            'odoo_synced_at' => now(),
        ]);

        Log::info('Odoo payment invoice synced.', [
            'payment_request' => $request->number,
            'odoo_invoice_number' => $odooNumber,
            'already_recorded' => $this->alreadyRecorded($body, $result),
            'has_pdf' => $pdf !== null,
        ]);

        return true;
    }

    public function buildPayload(PaymentRequest $request): array
    {
        $attempt = $request->paidAttempt;

        return [
            'id' => $request->id,
            'number' => $request->number,
            'customer_name' => $request->customer_name,
            'customer_mobile' => $request->customer_mobile,
            'note' => $request->description,
            'total' => round((float) $request->amount, 2),
            'currency' => $request->currency ?: 'SAR',
            'tax_included' => true,
            'payment_type' => ($attempt?->brand === 'MADA') ? 'Mada' : 'Visa',
            'payment_status' => 'Paid',
            'transaction_reference' => $request->gateway_transaction_id,
            'date_of_paid' => optional($request->paid_at)->format('Y-m-d H:i:s'),
        ];
    }

    /** Raw PDF bytes for download, or null. URLs are handled by the controller (redirect). */
    public function pdfContents(PaymentRequest $request): ?string
    {
        $path = (string) $request->odoo_invoice_pdf;
        if ($path === '' || str_starts_with($path, 'http')) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null;
    }

    /** Atomically move the request to "sending" so two workers never send it at the same time. */
    private function claim(PaymentRequest $request, bool $manual): bool
    {
        return DB::transaction(function () use ($request, $manual) {
            $locked = PaymentRequest::whereKey($request->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== PaymentRequest::STATUS_PAID || $locked->odoo_sync_status === PaymentRequest::ODOO_SYNCED) {
                return false;
            }

            $staleSending = $locked->odoo_sync_status === PaymentRequest::ODOO_SENDING
                && $locked->odoo_last_attempt_at
                && $locked->odoo_last_attempt_at->lt(now()->subMinutes(self::STALE_SENDING_MINUTES));

            if ($locked->odoo_sync_status === PaymentRequest::ODOO_SENDING && ! $staleSending) {
                return false;
            }

            if (! $manual && $locked->odoo_sync_attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            $locked->update([
                'odoo_sync_status' => PaymentRequest::ODOO_SENDING,
                'odoo_sync_attempts' => $locked->odoo_sync_attempts + 1,
                'odoo_last_attempt_at' => now(),
            ]);

            return true;
        });
    }

    private function fail(PaymentRequest $request, string $error): bool
    {
        $request->update([
            'odoo_sync_status' => PaymentRequest::ODOO_FAILED,
            'odoo_last_error' => Str::limit($error, 250),
        ]);

        Log::error('Odoo payment invoice sync failed.', [
            'payment_request' => $request->number,
            'attempt' => $request->odoo_sync_attempts,
            'error' => Str::limit($error, 500),
        ]);

        return false;
    }

    /** Same envelope handling as the booking sync: {"result": {...}} or {"data": {...}}. */
    private function resultData(array $body): array
    {
        foreach (['result', 'data'] as $key) {
            $candidate = $this->decodeArray(data_get($body, $key));
            if ($candidate === null) {
                continue;
            }

            $nested = $this->decodeArray(data_get($candidate, 'data'));

            return $nested === null ? $candidate : array_replace($candidate, $nested);
        }

        return $body;
    }

    private function rejected(array $body, array $result): bool
    {
        foreach ([$body, $result] as $candidate) {
            if (array_key_exists('status', $candidate)) {
                $status = $candidate['status'];
                if ($status === false || in_array(strtolower((string) $status), ['failed', 'error'], true)) {
                    return true;
                }
            }

            if (array_key_exists('success', $candidate) && $candidate['success'] === false) {
                return true;
            }

            if (! empty($candidate['error'])) {
                return true;
            }
        }

        return false;
    }

    private function alreadyRecorded(array $body, array $result): bool
    {
        foreach ([$body, $result] as $candidate) {
            if (! empty($candidate['already_recorded'])) {
                return true;
            }
        }

        return false;
    }

    private function invoiceNumber(array $result): ?string
    {
        foreach (['invoice_number', 'odoo_invoice_number', 'invoice_name', 'invoice.number', 'invoice.name', 'name', 'invoice_id', 'invoice.id'] as $key) {
            $value = data_get($result, $key);
            if ((is_string($value) && trim($value) !== '') || is_int($value)) {
                return Str::limit(trim((string) $value), 250, '');
            }
        }

        return null;
    }

    private function invoiceDocument(array $result): ?string
    {
        foreach ([
            'invoice_pdf', 'invoice_pdf_base64', 'invoice_document', 'invoice_document_base64',
            'invoice_pdf_url', 'invoice_url', 'invoice.pdf', 'invoice.pdf_base64', 'invoice.url', 'pdf',
        ] as $key) {
            $value = data_get($result, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * Keep the PDF private (storage/app), served only to admins and to the customer's
     * token link. Returns the stored path, the URL as-is, or null.
     */
    private function storePdf(PaymentRequest $request, ?string $document): ?string
    {
        if ($document === null) {
            return null;
        }

        if (str_starts_with($document, 'http://') || str_starts_with($document, 'https://')) {
            return Str::limit($document, 250, '');
        }

        $base64 = preg_replace('#^data:application/[^;]+;base64,#i', '', $document);
        $bytes = base64_decode(str_replace([' ', "\r", "\n"], '', (string) $base64), true);

        if ($bytes === false || ! str_starts_with($bytes, '%PDF')) {
            Log::warning('Odoo payment invoice: response PDF could not be decoded.', ['payment_request' => $request->number]);

            return null;
        }

        $path = 'payment-requests/odoo/'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $request->number).'.pdf';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    private function errorMessage(Response $response, array $body, array $result): string
    {
        foreach ([$body, $result] as $candidate) {
            foreach (['message', 'error.message', 'error.data.message', 'error', 'reason', 'detail'] as $key) {
                $value = data_get($candidate, $key);
                if (is_string($value) && trim($value) !== '') {
                    return 'HTTP '.$response->status().': '.$value;
                }
            }
        }

        return 'HTTP '.$response->status().': '.Str::limit(trim($response->body()), 200);
    }

    private function decodeArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
