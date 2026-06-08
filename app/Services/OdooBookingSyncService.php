<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Package\Models\Package;
use Modules\Booking\Models\Booking;
use Modules\Service\Models\Service;
use App\Models\GiftCard;
use App\Models\Invoice;

class OdooBookingSyncService
{
    public function syncPaidInvoice(int $invoiceId): bool
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

        $cartIds = $invoice->cart_ids;
        $giftIds = $invoice->gift_ids;
        
        if (is_string($cartIds)) {
            $cartIds = json_decode($cartIds, true);
        }

        if (is_string($giftIds)) {
            $giftIds = json_decode($giftIds, true);
        }

        $cartIds = array_values(array_filter((array) $cartIds));
        $giftIds = array_values(array_filter((array) ($giftIds ?? [])));
        
        if ($cartIds === []) {
            return false;
        }

        $bookings = collect();
        if ($cartIds !== []) {
            $bookings = Booking::with(['user', 'branch', 'service.service', 'service.employee', 'bookingTransaction'])
                ->whereIn('id', $cartIds)
                ->get();
        }

        $giftCards = collect();
        if ($giftIds !== []) {
            $giftCards = GiftCard::with('user')
                ->whereIn('id', $giftIds)
                ->get();
        }

        if ($bookings->isEmpty() && $giftCards->isEmpty()) {
            Log::warning('Odoo booking sync skipped: no paid records found for invoice.', [
                'invoice_id' => $invoiceId,
                'cart_ids' => $cartIds,
                'gift_ids' => $giftIds,
            ]);

            return false;
        }

        $payload = [
            'event' => 'odoo_create_booking',
            'data' => $this->buildPayload($invoice, $bookings, $giftCards),
        ];

        try {
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($jsonPayload === false) {
                throw new \RuntimeException('Failed to encode Odoo payload to JSON.');
            }

            $response = Http::timeout((int) config('services.odoo.timeout', 15))
                ->acceptJson()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'db' => $db,
                    'login' => $login,
                    'password' => $password,
                ])
                ->withBody($jsonPayload, 'application/json')
                ->send('POST', $url);

            if ($response->successful()) {
                Log::info('Odoo booking sync completed.', [
                    'invoice_id' => $invoiceId,
                    'status' => $response->status(),
                ]);

                return true;
            }

            if ($response->status() === 400 && str_contains(strtolower($response->body()), 'invalid csrf token')) {
                Log::error('Odoo booking sync rejected by Odoo CSRF protection.', [
                    'invoice_id' => $invoiceId,
                    'url' => $url,
                    'hint' => 'Configure the Odoo webhook route as csrf=False for external POST requests, or expose it as a JSON webhook endpoint.',
                ]);
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

    public function syncPaidCartBookings(int $invoiceId): bool
    {
        return $this->syncPaidInvoice($invoiceId);
    }

    private function buildPayload(Invoice $invoice, $bookings, $giftCards): array
    {
        $bookingSubTotal = (float) $bookings->sum(fn ($booking) => (float) ($booking->service->service_price ?? 0));
        $bookingTax = (float) (getBookingTaxamount($bookingSubTotal, 0, null)['total_tax_amount'] ?? 0);
        $bookingGross = $bookingSubTotal + $bookingTax;

        $giftSubTotal = (float) $giftCards->sum(fn ($giftCard) => (float) ($giftCard->subtotal ?? 0));
        $giftTax = (float) (getBookingTaxamount($giftSubTotal, 0, null)['total_tax_amount'] ?? 0);
        $giftGross = $giftSubTotal + $giftTax;

        $invoiceGross = max(((float) $invoice->final_total) + ((float) $invoice->discount_amount), 0);
        $bookingDiscount = $invoiceGross > 0
            ? round(((float) $invoice->discount_amount) * ($bookingGross / $invoiceGross), 2)
            : 0.0;
        $giftDiscount = $invoiceGross > 0
            ? round(((float) $invoice->discount_amount) * ($giftGross / $invoiceGross), 2)
            : 0.0;

        $bookingTotal = max($bookingGross - $bookingDiscount, 0);
        $giftTotal = max($giftGross - $giftDiscount, 0);

        $transaction = $bookings->first()?->bookingTransaction;
        $paymentDate = optional($invoice->created_at)->toDateString() ?? now()->toDateString();
        $transactionReference = $transaction->external_transaction_id ?? ('INV-' . $invoice->id);

        return [
            'id' => $invoice->id,
            'user_id' => (int) $invoice->user_id,
            'coupon_code' => $invoice->coupon_code,
            'coupon_discount' => $bookingDiscount,
            'gift_coupon_discount' => $giftDiscount,
            'sub_total' => $bookingSubTotal,
            'gift_sub_total' => $giftSubTotal,
            'tax' => $bookingTax,
            'gift_tax' => $giftTax,
            'total' => $bookingTotal,
            'gift_total' => $giftTotal,
            'has_gift_cards' => $giftCards->isNotEmpty(),
            'tapId' => $transactionReference,
            'booking_number' => $this->makeBookingNumber($invoice->id),
            'payment_type' => $this->normalizePaymentType($transaction->transaction_type ?? $invoice->payment_method),
            'payment_status' => 'Paid',
            'date_of_paid' => $paymentDate,
            'booking_details' => $this->buildBookingDetails($invoice, $bookings),
            'gift_card_details' => $this->buildGiftCardDetails($invoice, $giftCards, $paymentDate),
        ];
    }

    private function buildBookingDetails(Invoice $invoice, $bookings): array
    {
        return $bookings->map(function ($booking) use ($invoice) {
            $bookingService = $booking->service;
            $service = $bookingService?->service;
            $employee = $bookingService?->employee;
            $branch = $booking->branch;
            $user = $booking->user ?? $invoice->user;
            $serviceImage = $service->feature_image ?? null;

            return [
                'id' => $booking->id,
                'user_id' => (int) $booking->user_id,
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
        })->values()->all();
    }

    private function buildGiftCardDetails(Invoice $invoice, $giftCards, string $paymentDate): array
    {
        if ($giftCards->isEmpty()) {
            return [];
        }

        $serviceIds = $giftCards->pluck('requested_services')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $packageIds = $giftCards->pluck('package_ids')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $packages = Package::whereIn('id', $packageIds)->get()->keyBy('id');

        return $giftCards->map(function ($giftCard) use ($invoice, $paymentDate, $services, $packages) {
            $user = $giftCard->user ?? $invoice->user;
            $requestedServiceIds = collect((array) $giftCard->requested_services)->filter()->values();
            $requestedPackageIds = collect((array) $giftCard->package_ids)->filter()->values();

            return [
                'id' => $giftCard->id,
                'invoice_id' => $invoice->id,
                'user_id' => (int) $giftCard->user_id,
                'ref' => $giftCard->ref,
                'buyer_name' => $user?->full_name,
                'buyer_email' => $user?->email,
                'buyer_phone' => $user?->mobile,
                'sender_name' => $giftCard->sender_name,
                'sender_phone' => $giftCard->sender_phone,
                'recipient_name' => $giftCard->recipient_name,
                'recipient_phone' => $giftCard->recipient_phone,
                'delivery_method' => $this->normalizeDeliveryMethod($giftCard->delivery_method),
                'message' => $giftCard->message,
                'amount' => (float) ($giftCard->subtotal ?? 0),
                'balance' => (float) ($giftCard->balance ?? 0),
                'payment_status' => 'Paid',
                'date_of_paid' => $paymentDate,
                'services' => $requestedServiceIds->map(function ($serviceId) use ($services) {
                    $service = $services->get($serviceId);

                    return [
                        'id' => $serviceId,
                        'odoo_id' => $service->odoo_id ?? null,
                        'name' => $this->resolveName($service->name ?? null),
                        'price' => (float) ($service->default_price ?? 0),
                    ];
                })->values()->all(),
                'packages' => $requestedPackageIds->map(function ($packageId) use ($packages) {
                    $package = $packages->get($packageId);

                    return [
                        'id' => $packageId,
                        'name' => $this->resolveName($package->name ?? null),
                        'price' => (float) ($package->package_price ?? 0),
                    ];
                })->values()->all(),
                'coupons' => (array) ($giftCard->coupons ?? []),
            ];
        })->values()->all();
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
            'tap' => 'Card',
            'wallet' => 'Wallet',
            'urpay' => 'UrPay',
            'tabby' => 'Tabby',
            'tamara' => 'Tamara',
            'sub_methods' => 'Wallet/Loyalty/Gift',
            default => ucfirst($paymentType),
        };
    }

    private function normalizeDeliveryMethod(?string $deliveryMethod): string
    {
        return match (trim((string) $deliveryMethod)) {
            'electronic_card', 'email', 'بطاقة الكترونية' => 'Electronic Card',
            'traditional', 'center_pickup', 'استلام من المركز' => 'Center Pickup',
            default => ucfirst((string) $deliveryMethod),
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
