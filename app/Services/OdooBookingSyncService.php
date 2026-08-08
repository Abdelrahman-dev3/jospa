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
        $authHeaders = app(OdooGiftCardService::class)->authHeaders();

        if ($url === '') {
            Log::warning('Odoo booking sync skipped: missing booking_create_url.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        if (empty($authHeaders)) {
            Log::warning('Odoo booking sync skipped: missing authentication.', [
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
        
        if ($cartIds === [] && $giftIds === []) {
            return false;
        }

        $bookings = collect();
        if ($cartIds !== []) {
            $bookings = Booking::with(['user', 'branch', 'services.service', 'services.employee', 'bookingTransaction'])
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

        $payload = $this->buildRequestPayload(
            $url,
            $this->buildPayload($invoice, $bookings, $giftCards)
        );

        try {
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($jsonPayload === false) {
                throw new \RuntimeException('Failed to encode Odoo payload to JSON.');
            }

            $response = Http::timeout((int) config('services.odoo.timeout', 15))
                ->acceptJson()
                ->withHeaders(array_merge([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ], $authHeaders))
                ->withBody($jsonPayload, 'application/json')
                ->send('POST', $url);

            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body) && (
                    (isset($body['status']) && ($body['status'] === false || $body['status'] === 'failed' || $body['status'] === 'error'))
                    || (isset($body['valid']) && $body['valid'] === false)
                    || (!empty($body['error']))
                )) {
                    $errorMsg = $this->extractErrorMessage($response);
                    Log::error('Odoo booking sync rejected by Odoo response.', [
                        'invoice_id' => $invoiceId,
                        'response' => $body,
                    ]);
                    throw new \RuntimeException($errorMsg);
                }

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

            $errorMsg = $this->extractErrorMessage($response);
            Log::error('Odoo booking sync failed.', [
                'invoice_id' => $invoiceId,
                'status' => $response->status(),
                'response' => $response->body(),
                'payload' => $payload,
            ]);

            throw new \RuntimeException($errorMsg);
        } catch (\Throwable $e) {
            Log::error('Odoo booking sync exception.', [
                'invoice_id' => $invoiceId,
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }

        return false;
    }

    public function syncPaidCartBookings(int $invoiceId): bool
    {
        return $this->syncPaidInvoice($invoiceId);
    }

    private function buildRequestPayload(string $url, array $data): array
    {
        if (str_contains($url, '/odoo/order/create')) {
            return [
                'data' => $data,
            ];
        }

        return [
            'event' => 'odoo_create_booking',
            'data' => $data,
        ];
    }

    private function buildPayload(Invoice $invoice, $bookings, $giftCards): array
    {
        $bookingSubTotal = (float) $bookings->sum(fn ($booking) => (float) ($booking->services->count() ? $booking->services->sum('service_price') : ($booking->service->service_price ?? 0)));
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
        $firstBooking = $bookings->first();
        $customer = $firstBooking?->user ?? $invoice->user;

        $payload = [
            'id' => $invoice->id,
            'payment_type_code' => $this->resolvePaymentTypeCode($transaction->transaction_type ?? $invoice->payment_method),
            'user_id' => (int) $invoice->user_id,
            'client_name' => $this->resolveCustomerName($customer),
            'client_mobile' => $customer?->mobile,
            'client_email' => $customer?->email,
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
            'booking_date' => $this->formatDate($firstBooking?->start_date_time, 'd/m/Y'),
            'booking_time' => $this->formatDate($firstBooking?->start_date_time, 'H:i'),
            'order_services' => $this->buildOrderServices($bookings),
            'booking_details' => $this->buildBookingDetails($invoice, $bookings),
            'gift_card_details' => $this->buildGiftCardDetails($invoice, $giftCards, $paymentDate),
        ];

        $redeemedGiftCard = $this->buildRedeemedGiftCard($invoice);
        if ($redeemedGiftCard !== null) {
            $payload['gift_card'] = $redeemedGiftCard;
            $payload['giftcard_code'] = $redeemedGiftCard['code'];
            $payload['giftcard_amount'] = (float) $redeemedGiftCard['amount'];
        }

        return $payload;
    }

    private function buildBookingDetails(Invoice $invoice, $bookings): array
    {
        $details = [];
        foreach ($bookings as $booking) {
            $services = $booking->services->count() ? $booking->services : collect([$booking->service]);
            foreach ($services as $bookingService) {
                if (!$bookingService) continue;
                $service = $bookingService->service;
                $employee = $bookingService->employee;
                $branch = $booking->branch;
                $user = $booking->user ?? $invoice->user;
                $serviceImage = $service->feature_image ?? null;

                $details[] = [
                    'id' => $booking->id,
                    'user_id' => (int) $booking->user_id,
                    'bookingInfo_id' => $invoice->id,
                    'booking_date' => $this->formatDate($booking->start_date_time, 'Y-m-d'),
                    'booking_time' => $this->formatDate($booking->start_date_time, 'H:i:s'),
                    'client_name' => $user?->first_name . ' ' . $user?->last_name,
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
                        'name' => $employee?->first_name . ' ' . $employee?->last_name,
                    ],
                    'location' => [
                        'id' => $branch->id ?? null,
                        'name' => $this->resolveName($branch->name ?? null),
                    ],
                ];
            }
        }
        return $details;
    }

    private function buildOrderServices($bookings): array
    {
        $orderServices = [];
        foreach ($bookings as $booking) {
            $services = $booking->services->count() ? $booking->services : collect([$booking->service]);
            foreach ($services as $bookingService) {
                if (!$bookingService) continue;
                $service = $bookingService->service;
                $orderServices[] = [
                    'id' => (int) ($service->odoo_id ?? $service->id ?? $bookingService->id ?? $booking->id),
                    'quantity' => 1,
                    'price' => (float) ($bookingService->service_price ?? 0),
                ];
            }
        }
        return $orderServices;
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
                'buyer_name' => $user?->first_name . ' ' . $user?->last_name,
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

    private function buildRedeemedGiftCard(Invoice $invoice): ?array
    {
        $code = trim((string) $invoice->gift_code);
        $amount = (float) ($invoice->gift_amount ?? 0);

        if ($code === '') {
            return null;
        }

        return [
            'code' => $code,
            'amount' => round($amount, 2),
        ];
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

    private function resolvePaymentTypeCode(?string $paymentType): string
    {
        $configuredCode = trim((string) config('services.odoo.payment_type_code'));
        if ($configuredCode !== '') {
            return $configuredCode;
        }

        $paymentType = strtolower(trim((string) $paymentType));

        return match ($paymentType) {
            'card', 'tap' => 'CARD',
            'wallet' => 'WALLET',
            'urpay', 'stcpay', 'applepay_urpay' => 'URPAY',
            'tabby' => 'TABBY',
            'tamara' => 'TAMARA',
            default => $paymentType !== '' ? strtoupper($paymentType) : 'CSH1',
        };
    }

    private function resolveCustomerName($user): ?string
    {
        if (! $user) {
            return null;
        }

        $name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));

        return $name !== '' ? $name : ($user->full_name ?? $user->name ?? null);
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

    private function extractErrorMessage(Response $response): string
    {
        $body = $response->json();
        if (is_array($body)) {
            if (! empty($body['message']) && is_string($body['message'])) {
                return $body['message'];
            }
            if (! empty($body['error']) && is_string($body['error'])) {
                return $body['error'];
            }
            if (! empty($body['reason']) && is_string($body['reason'])) {
                return $body['reason'];
            }
            if (! empty($body['detail']) && is_string($body['detail'])) {
                return $body['detail'];
            }
            if (isset($body['error']['message']) && is_string($body['error']['message'])) {
                return $body['error']['message'];
            }
            if (isset($body['error']['data']['message']) && is_string($body['error']['data']['message'])) {
                return $body['error']['data']['message'];
            }
        }

        $rawBody = trim($response->body());
        if ($rawBody !== '') {
            return $rawBody;
        }

        return 'Failed to synchronize invoice with Odoo.';
    }
}
