<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
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
        
        if ($cartIds === [] && $giftIds === []) {
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

            Log::info('Initiating Odoo booking sync for paid invoice.', [
                'invoice_id' => $invoiceId,
                'url' => $url,
            ]);

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
                $body = $response->json();
                $resultData = is_array($body) ? $this->resolveOdooResultData($body) : [];
                $invoiceDocument = $this->extractInvoiceDocument($resultData);
                $giftCardsCreated = $this->extractGiftCardsCreated($resultData);
                $giftCardRedeemed = $this->extractGiftCardRedeemed($resultData);
                
                Log::info('Odoo booking sync response received.', [
                    'invoice_id' => $invoiceId,
                    'status' => $response->status(),
                    'response_keys' => array_keys($resultData),
                    'has_invoice_pdf' => filled($invoiceDocument),
                    'invoice_pdf_bytes' => is_string($invoiceDocument) ? strlen($invoiceDocument) : 0,
                    'gift_cards_created_count' => count($giftCardsCreated),
                    'gift_card_pdfs_count' => count(array_filter($giftCardsCreated, fn ($giftItem) => is_array($giftItem) && filled($this->extractGiftCardDocument($giftItem)))),
                    'has_gift_card_redeemed' => is_array($giftCardRedeemed),
                    'response_body' => $this->sanitizeResponseForLog($body),
                ]);

                if (is_array($body) && (
                    (isset($body['status']) && ($body['status'] === false || $body['status'] === 'failed' || $body['status'] === 'error'))
                    || (isset($body['valid']) && $body['valid'] === false)
                    || (!empty($body['error']))
                )) {
                    $errorMsg = $this->extractErrorMessage($response);
                    Log::error('Odoo booking sync rejected by Odoo response.', [
                        'invoice_id' => $invoiceId,
                        'response' => $this->sanitizeResponseForLog($body),
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

            $data = $this->resolveOdooResultData($responseBody);

            // 1. Send Invoice PDF to the buyer / payer (اللي دفع)
            $invoiceDocument = $this->extractInvoiceDocument($data);
            if (filled($invoiceDocument) && filled($buyerPhone)) {
                $invoiceFilename = "Invoice_INV-{$invoice->id}.pdf";
                $caption = "مرحباً {$buyerName}، مرفق لك فاتورة عملية الدفع رقم INV-{$invoice->id} من جو سبا (JO SPA). شكراً لاختيارك لنا!";

                $sentInvoice = $whatsAppService->sendDocument(
                    phone: (string) $buyerPhone,
                    fileUrlOrBase64: (string) $invoiceDocument,
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
                    'has_pdf' => filled($invoiceDocument),
                    'has_phone' => filled($buyerPhone),
                    'response_keys' => array_keys($data),
                ]);
            }

            // 2. Send Gift Card PDFs to Recipients (المهدي اليه)
            $giftCardsCreated = $this->extractGiftCardsCreated($data);
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

                    $giftDocument = $this->extractGiftCardDocument($giftItem);
                    $code = $this->firstFilledString($giftItem, [
                        'code',
                        'ref',
                        'gift_code',
                        'gift_card_code',
                        'card_code',
                        'coupon_code',
                    ]);
                    if (blank($giftDocument)) {
                        Log::warning('Gift card created PDF is missing in Odoo response.', [
                            'invoice_id' => $invoice->id,
                            'index' => $index,
                            'code' => $code,
                            'gift_item_keys' => array_keys($giftItem),
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
                        fileUrlOrBase64: (string) $giftDocument,
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
            $giftCardRedeemed = $this->extractGiftCardRedeemed($data);
            $redeemedDocument = is_array($giftCardRedeemed) ? $this->extractGiftCardDocument($giftCardRedeemed) : null;
            if (is_array($giftCardRedeemed) && filled($redeemedDocument) && filled($buyerPhone)) {
                $redeemedCode = $this->firstFilledString($giftCardRedeemed, [
                    'code',
                    'ref',
                    'gift_code',
                    'gift_card_code',
                    'card_code',
                    'coupon_code',
                ]) ?? 'GC';
                $remainingBalance = data_get($giftCardRedeemed, 'remaining_balance', data_get($giftCardRedeemed, 'balance', 0));

                $redeemedFilename = "Redeemed_GiftCard_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $redeemedCode) . ".pdf";
                $redeemedCaption = "مرحباً {$buyerName}، تم استخدام بطاقة الهدايا ({$redeemedCode}) بنجاح.\nالرصيد المتبقي في البطاقة: {$remainingBalance} ر.س.\nمرفق لكم تفاصيل البطاقة والمبلغ المتبقي.";

                $sentRedeemed = $whatsAppService->sendDocument(
                    phone: (string) $buyerPhone,
                    fileUrlOrBase64: (string) $redeemedDocument,
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

    private function resolveOdooResultData(array $responseBody): array
    {
        foreach (['result', 'data', 'response'] as $key) {
            if (isset($responseBody[$key]) && is_array($responseBody[$key])) {
                return $responseBody[$key];
            }
        }

        return $responseBody;
    }

    private function extractInvoiceDocument(array $data): ?string
    {
        return $this->firstFilledDocument($data, [
            'invoice_pdf',
            'invoice_pdf_base64',
            'invoice_base64',
            'invoice_attachment',
            'invoice_url',
            'invoice_pdf_url',
            'invoice_file',
            'invoice_file_url',
            'invoice.pdf',
            'invoice.pdf_base64',
            'invoice.invoice_pdf',
            'invoice.invoice_pdf_base64',
            'invoice.attachment',
            'invoice.attachment_base64',
            'invoice.url',
            'invoice.pdf_url',
            'invoice.file',
            'invoice.file_url',
            'data.invoice_pdf',
            'data.invoice_pdf_base64',
            'data.invoice.pdf',
            'data.invoice.pdf_base64',
            'pdf',
            'pdf_base64',
            'pdf_url',
            'file_url',
            'download_url',
        ]);
    }

    private function extractGiftCardsCreated(array $data): array
    {
        foreach ([
            'gift_cards_created',
            'gift_cards',
            'giftCards',
            'created_gift_cards',
            'createdGiftCards',
            'gift_card_created',
            'giftCardCreated',
            'gift_card_details',
            'giftCardDetails',
            'gift_card',
            'giftCard',
            'data.gift_cards_created',
            'data.gift_cards',
            'data.gift_card',
        ] as $path) {
            $value = data_get($data, $path);
            if (! is_array($value) || empty($value)) {
                continue;
            }

            if ($this->isListArray($value)) {
                $items = array_values(array_filter($value, fn ($item) => is_array($item)));
                if (! empty($items)) {
                    return $items;
                }

                continue;
            }

            if ($this->looksLikeSingleGiftCardResponse($value)) {
                return [$value];
            }
        }

        $singleGiftDocument = $this->firstFilledDocument($data, [
            'gift_card_pdf',
            'gift_card_pdf_base64',
            'gift_pdf',
            'gift_pdf_base64',
            'gift_card.pdf',
            'gift_card.pdf_base64',
            'gift_card.url',
            'gift_card.pdf_url',
            'data.gift_card_pdf',
            'data.gift_card.pdf',
        ]);

        if (filled($singleGiftDocument)) {
            return [[
                'pdf' => $singleGiftDocument,
                'code' => $this->firstFilledString($data, [
                    'gift_card_code',
                    'gift_code',
                    'card_code',
                    'code',
                    'ref',
                    'gift_card.code',
                    'gift_card.ref',
                    'data.gift_card.code',
                    'data.gift_card.ref',
                ]),
            ]];
        }

        return [];
    }

    private function extractGiftCardRedeemed(array $data): ?array
    {
        foreach ([
            'gift_card_redeemed',
            'redeemed_gift_card',
            'redeemedGiftCard',
            'gift_card_used',
            'used_gift_card',
            'data.gift_card_redeemed',
            'data.redeemed_gift_card',
        ] as $path) {
            $value = data_get($data, $path);
            if (is_array($value) && ! empty($value)) {
                return $value;
            }
        }

        return null;
    }

    private function extractGiftCardDocument(array $giftItem): ?string
    {
        return $this->firstFilledDocument($giftItem, [
            'pdf',
            'pdf_base64',
            'pdf_url',
            'gift_card_pdf',
            'gift_card_pdf_base64',
            'gift_pdf',
            'gift_pdf_base64',
            'card_pdf',
            'coupon_pdf',
            'document',
            'document_base64',
            'document_url',
            'attachment',
            'attachment_base64',
            'file',
            'file_url',
            'download_url',
            'gift_card.pdf',
            'gift_card.pdf_base64',
            'gift_card.url',
            'gift_card.pdf_url',
        ], true);
    }

    private function firstFilledDocument(array $data, array $paths, bool $allowRecursiveFallback = false): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($data, $path);
            if ($this->isDocumentValue($value)) {
                return trim((string) $value);
            }
        }

        return $allowRecursiveFallback ? $this->findDocumentValueRecursive($data) : null;
    }

    private function firstFilledString(array $data, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($data, $path);
            if (is_scalar($value) && filled((string) $value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function findDocumentValueRecursive(array $data): ?string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $nested = $this->findDocumentValueRecursive($value);
                if ($nested !== null) {
                    return $nested;
                }

                continue;
            }

            if ($this->looksLikeDocumentKey($key) && $this->isDocumentValue($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function looksLikeSingleGiftCardResponse(array $value): bool
    {
        return $this->extractGiftCardDocument($value) !== null
            || $this->firstFilledString($value, ['code', 'ref', 'gift_code', 'gift_card_code', 'card_code']) !== null;
    }

    private function isListArray(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private function looksLikeDocumentKey(string|int $key): bool
    {
        return is_string($key) && preg_match('/pdf|document|attachment|file|download_url|url|base64/i', $key) === 1;
    }

    private function isDocumentValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return true;
        }

        $base64Data = preg_replace('#^data:application/[\w.+-]+;base64,#i', '', $value);
        $base64Data = str_replace([' ', "\r", "\n"], '', (string) $base64Data);

        if (str_starts_with($base64Data, 'JVBER')) {
            return true;
        }

        return strlen($base64Data) > 1000
            && preg_match('/^[A-Za-z0-9+\/=]+$/', $base64Data) === 1;
    }

    private function sanitizeResponseForLog(mixed $body): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

        $sanitized = [];
        foreach ($body as $key => $value) {
            if ($this->looksLikeDocumentKey($key) && $this->isDocumentValue($value)) {
                $sanitized[$key] = '[BASE64_PDF_LENGTH_' . strlen($value) . '_BYTES]';
            } elseif ($key === 'gift_cards_created' && is_array($value)) {
                $sanitized[$key] = array_map(function ($item) {
                    if (is_array($item)) {
                        return $this->sanitizeResponseForLog($item);
                    }

                    return $item;
                }, $value);
            } elseif ($key === 'gift_card_redeemed' && is_array($value)) {
                $sanitized[$key] = $this->sanitizeResponseForLog($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeResponseForLog($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
