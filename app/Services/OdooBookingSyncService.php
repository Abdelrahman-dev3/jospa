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
use App\Services\WhatsApp\JavnaWhatsAppService;

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

                if (is_array($body)) {
                    $this->handleOdooPaymentResponse($invoice, $body);
                }

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

    /**
     * Process Odoo payment response and send WhatsApp PDFs:
     * - Invoice PDF to the buyer/payer (اللي دفع)
     * - Gift card PDFs to the recipients (المهدي اليه)
     * - Redeemed gift card PDF to the buyer (if any)
     */
    private function handleOdooPaymentResponse(Invoice $invoice, array $responseBody): void
    {
        try {
            $user = $invoice->user;
            $buyerPhone = $user?->mobile;
            $buyerName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'عميلنا العزيز';
            if ($buyerName === '') {
                $buyerName = $user->full_name ?? 'عميلنا العزيز';
            }

            $whatsAppService = app(JavnaWhatsAppService::class);

            // 1. Send Invoice PDF to the buyer / payer (اللي دفع)
            $invoicePdfBase64 = $responseBody['invoice_pdf'] ?? null;
            if (filled($invoicePdfBase64) && filled($buyerPhone)) {
                $invoiceFilename = "Invoice_INV-{$invoice->id}.pdf";
                $caption = "مرحباً {$buyerName}، مرفق لك فاتورة عملية الدفع رقم INV-{$invoice->id} من جو سبا (JO SPA). شكراً لاختيارك لنا!";

                $sentInvoice = $whatsAppService->sendDocument(
                    phone: (string) $buyerPhone,
                    fileUrlOrBase64: (string) $invoicePdfBase64,
                    filename: $invoiceFilename,
                    caption: $caption
                );

                Log::info('Invoice PDF WhatsApp sent from Odoo response.', [
                    'invoice_id' => $invoice->id,
                    'buyer_phone' => $buyerPhone,
                    'sent' => $sentInvoice,
                ]);
            } else {
                Log::warning('Invoice PDF sending skipped: missing base64 or buyer phone.', [
                    'invoice_id' => $invoice->id,
                    'has_pdf' => filled($invoicePdfBase64),
                    'has_phone' => filled($buyerPhone),
                ]);
            }

            // 2. Send Gift Card PDFs to Recipients (المهدي اليه)
            $giftCardsCreated = $responseBody['gift_cards_created'] ?? [];
            if (is_array($giftCardsCreated) && ! empty($giftCardsCreated)) {
                $giftIds = $invoice->gift_ids;
                if (is_string($giftIds)) {
                    $giftIds = json_decode($giftIds, true);
                }
                $giftIds = array_values(array_filter((array) ($giftIds ?? [])));

                $localGiftCards = GiftCard::whereIn('id', $giftIds)->get()->keyBy('id');

                foreach ($giftCardsCreated as $index => $giftItem) {
                    if (! is_array($giftItem)) {
                        continue;
                    }

                    $giftPdfBase64 = $giftItem['pdf'] ?? null;
                    $code = $giftItem['code'] ?? null;
                    $amount = $giftItem['amount'] ?? 0;
                    $isGift = ! empty($giftItem['is_gift']);

                    if (blank($giftPdfBase64)) {
                        Log::warning('Gift card created PDF is missing in Odoo response.', [
                            'invoice_id' => $invoice->id,
                            'index' => $index,
                            'code' => $code,
                        ]);
                        continue;
                    }

                    // Match local GiftCard record
                    $matchingGiftCard = null;
                    if ($code !== null) {
                        $matchingGiftCard = $localGiftCards->first(fn ($g) => $g->ref === $code);
                    }
                    if (! $matchingGiftCard && isset($giftIds[$index])) {
                        $matchingGiftCard = $localGiftCards->get($giftIds[$index]);
                    }
                    if (! $matchingGiftCard && $localGiftCards->isNotEmpty()) {
                        $matchingGiftCard = $localGiftCards->values()->get($index) ?? $localGiftCards->first();
                    }

                    $recipientPhone = $matchingGiftCard?->recipient_phone;
                    $recipientName = $matchingGiftCard?->recipient_name ?? 'عزيزنا/عزيزتنا';
                    $senderName = $matchingGiftCard?->sender_name ?? $buyerName;
                    $personalMessage = trim((string) ($matchingGiftCard?->message ?? ''));

                    // Send to Recipient (المهدي اليه) phone, fallback to sender/buyer phone if recipient phone not set
                    $targetPhone = filled($recipientPhone) ? $recipientPhone : ($matchingGiftCard?->sender_phone ?: $buyerPhone);

                    if (blank($targetPhone)) {
                        Log::warning('Gift card PDF WhatsApp skipped: target phone number missing.', [
                            'invoice_id' => $invoice->id,
                            'code' => $code,
                        ]);
                        continue;
                    }

                    $giftCardFilename = "GiftCard_" . ($code ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $code) : ($index + 1)) . ".pdf";

                    $giftCaption = "مرحباً {$recipientName}، لقد تلقيت بطاقة إهداء من {$senderName} من جو سبا (JO SPA)!";
                    if ($personalMessage !== '') {
                        $giftCaption .= "\nالرسالة المرفقة: \"{$personalMessage}\"";
                    }
                    $giftCaption .= "\nمرفق لكم بطاقة الإهداء الخاصة بكم.";

                    $sentGift = $whatsAppService->sendDocument(
                        phone: (string) $targetPhone,
                        fileUrlOrBase64: (string) $giftPdfBase64,
                        filename: $giftCardFilename,
                        caption: $giftCaption
                    );

                    Log::info('Gift card PDF WhatsApp sent from Odoo response.', [
                        'invoice_id' => $invoice->id,
                        'code' => $code,
                        'recipient_name' => $recipientName,
                        'target_phone' => $targetPhone,
                        'sent' => $sentGift,
                    ]);
                }
            }

            // 3. Send Redeemed Gift Card PDF (if present) to the Buyer (اللي دفع)
            $giftCardRedeemed = $responseBody['gift_card_redeemed'] ?? null;
            if (is_array($giftCardRedeemed) && filled($giftCardRedeemed['pdf'] ?? null) && filled($buyerPhone)) {
                $redeemedPdfBase64 = $giftCardRedeemed['pdf'];
                $redeemedCode = $giftCardRedeemed['code'] ?? 'GC';
                $remainingBalance = $giftCardRedeemed['remaining_balance'] ?? 0;

                $redeemedFilename = "Redeemed_GiftCard_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $redeemedCode) . ".pdf";
                $redeemedCaption = "مرحباً {$buyerName}، تم استخدام بطاقة الهدايا ({$redeemedCode}) بنجاح.\nالرصيد المتبقي في البطاقة: {$remainingBalance} ر.س.\nمرفق لكم تفاصيل البطاقة والمبلغ المتبقي.";

                $sentRedeemed = $whatsAppService->sendDocument(
                    phone: (string) $buyerPhone,
                    fileUrlOrBase64: (string) $redeemedPdfBase64,
                    filename: $redeemedFilename,
                    caption: $redeemedCaption
                );

                Log::info('Redeemed gift card PDF WhatsApp sent from Odoo response.', [
                    'invoice_id' => $invoice->id,
                    'code' => $redeemedCode,
                    'remaining_balance' => $remainingBalance,
                    'buyer_phone' => $buyerPhone,
                    'sent' => $sentRedeemed,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Error handling Odoo payment response for WhatsApp delivery.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
