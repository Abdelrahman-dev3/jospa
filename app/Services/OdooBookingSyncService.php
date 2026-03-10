<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;

class OdooBookingSyncService
{
    public function syncPaidCartBookings(int $invoiceId): bool
    {
        $url = (string) config('services.odoo.booking_create_url');
        $db = (string) config('services.odoo.db');
        $login = (string) config('services.odoo.login');
        $password = (string) config('services.odoo.password');

        if ($url === '') {
            Log::warning('Odoo booking sync skipped: missing booking_create_url.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        if ($db === '' || $login === '' || $password === '') {
            Log::warning('Odoo booking sync skipped: missing credentials.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        $invoice = Invoice::with('user')->find($invoiceId);
        if (! $invoice) {
            Log::warning('Odoo booking sync skipped: invoice not found.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        $cartIds = array_values(array_filter((array) ($invoice->cart_ids ?? [])));
        if ($cartIds === []) {
            return false;
        }

        $bookings = Booking::with([
            'user',
            'branch',
            'service.service',
            'service.employee',
            'bookingTransaction',
        ])->whereIn('id', $cartIds)->get();

        if ($bookings->isEmpty()) {
            Log::warning('Odoo booking sync skipped: no bookings found for invoice.', [
                'invoice_id' => $invoiceId,
                'cart_ids' => $cartIds,
            ]);

            return false;
        }

        $payload = [
            'event' => 'odoo_create_booking',
            'data' => $this->buildPayload($invoice, $bookings),
        ];

        try {
            $response = Http::timeout((int) config('services.odoo.timeout', 15))
                ->withHeaders([
                    'db' => $db,
                    'login' => $login,
                    'password' => $password,
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('Odoo booking sync completed.', [
                    'invoice_id' => $invoiceId,
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::error('Odoo booking sync failed.', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'response' => $response->body(),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('Odoo booking sync exception.', [
                'invoice_id' => $invoiceId,
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return false;
    }

    private function buildPayload(Invoice $invoice, $bookings): array
    {
        $bookingSubTotal = (float) $bookings->sum(function ($booking) {
            return (float) ($booking->service->service_price ?? 0);
        });

        $bookingTax = (float) (getBookingTaxamount($bookingSubTotal, 0, null)['total_tax_amount'] ?? 0);
        $bookingGross = $bookingSubTotal + $bookingTax;
        $invoiceGross = max(((float) $invoice->final_total) + ((float) $invoice->discount_amount), 0);
        $bookingDiscount = $invoiceGross > 0
            ? round(((float) $invoice->discount_amount) * ($bookingGross / $invoiceGross), 2)
            : 0.0;
        $bookingTotal = max($bookingGross - $bookingDiscount, 0);

        $transaction = $bookings->first()->bookingTransaction;
        $paymentDate = optional($invoice->created_at)->toDateString() ?? now()->toDateString();

        return [
            'id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'coupon_code' => $invoice->coupon_code,
            'coupon_discount' => $bookingDiscount,
            'sub_total' => $bookingSubTotal,
            'tax' => $bookingTax,
            'total' => $bookingTotal,
            'tapId' => $transaction->external_transaction_id ?? null,
            'booking_number' => $this->makeBookingNumber($invoice->id),
            'payment_type' => $this->normalizePaymentType($transaction->transaction_type ?? null),
            'payment_status' => 'Paid',
            'date_of_paid' => $paymentDate,
            'booking_details' => $bookings->map(function ($booking) use ($invoice) {
                $bookingService = $booking->service;
                $service = $bookingService?->service;
                $employee = $bookingService?->employee;
                $branch = $booking->branch;
                $user = $booking->user ?? $invoice->user;
                $serviceImage = $service->feature_image ?? null;

                return [
                    'id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'bookingInfo_id' => $invoice->id,
                    'booking_date' => $this->formatDate($booking->start_date_time, 'Y-m-d'),
                    'booking_time' => $this->formatDate($booking->start_date_time, 'H:i:s'),
                    'client_name' => $user?->full_name,
                    'client_email' => $user?->email,
                    'client_phone' => $user?->mobile,
                    'notes' => $booking->note,
                    'service' => [
                        'id' => $service->id ?? null,
                        'odoo_id' => $service->odoo_id ?? null,
                        'name' => $this->resolveName($service->name ?? null),
                        'image1' => $serviceImage,
                        'image2' => null,
                    ],
                    'pricing' => [
                        'id' => $bookingService->id ?? null,
                        'name' => $this->resolveName($service->name ?? null),
                        'price' => (float) ($bookingService->service_price ?? 0),
                        'image' => $serviceImage,
                    ],
                    'employee' => [
                        'id' => $employee->id ?? null,
                        'name' => $employee?->full_name,
                    ],
                    'location' => [
                        'id' => $branch->id ?? null,
                        'name' => $this->resolveName($branch->name ?? null),
                    ],
                ];
            })->values()->all(),
        ];
    }

    private function makeBookingNumber(int $invoiceId): string
    {
        return '#JO-' . now()->format('Ymd') . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT);
    }

    private function normalizePaymentType(?string $paymentType): string
    {
        $paymentType = trim((string) $paymentType);

        if ($paymentType === '') {
            return 'Unknown';
        }

        return match (strtolower($paymentType)) {
            'card' => 'Card',
            'wallet' => 'Wallet',
            'tabby' => 'Tabby',
            'tamara' => 'Tamara',
            'sub_methods' => 'Wallet/Loyalty/Gift',
            default => ucfirst($paymentType),
        };
    }

    private function resolveName($value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $locale = app()->getLocale();

            if (! empty($value[$locale])) {
                return $value[$locale];
            }

            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    return $item;
                }
            }
        }

        return null;
    }

    private function formatDate($value, string $format): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format($format);
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
