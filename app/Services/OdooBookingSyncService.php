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
                
                Log::info('Odoo booking sync response received.', [
                    'invoice_id' => $invoiceId,
                    'status' => $response->status(),
                    'has_invoice_pdf' => ! empty($body['invoice_pdf']),
                    'invoice_pdf_bytes' => isset($body['invoice_pdf']) && is_string($body['invoice_pdf']) ? strlen($body['invoice_pdf']) : 0,
                    'gift_cards_created_count' => is_array($body['gift_cards_created'] ?? null) ? count($body['gift_cards_created']) : 0,
                    'has_gift_card_redeemed' => ! empty($body['gift_card_redeemed']),
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
     * - Invoice PDF to the buyer/payer (اللي دفع)  via jospa_invoice_pdf_sa template
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
            //    Uses the jospa_invoice_pdf_sa WhatsApp template (document header + 6 body vars).
            //    Falls back to plain sendDocument inside JavnaWhatsAppService when the template fails.
            $invoiceDocument = $this->extractInvoiceDocument($data);
            if (filled($invoiceDocument) && filled($buyerPhone)) {
                $invoiceFilename = "Invoice_INV-{$invoice->id}.pdf";

                // Build the 6 template body variables (same order as the template definition)
                $invoiceTemplateVariables = $this->buildInvoiceTemplateVariables($invoice, $buyerName, $data);

                if ($whatsAppService->shouldUseInvoicePdfTemplate()) {
                    $sentInvoice = $whatsAppService->sendTemplateWithDocument(
                        phone:     (string) $buyerPhone,
                        fileUrlOrBase64: (string) $invoiceDocument,
                        filename:  $invoiceFilename,
                        variables: $invoiceTemplateVariables,
                    );
                } else {
                    // Fallback: send as plain document with a caption
                    $caption = "مرحباً {$buyerName}، مرفق لك فاتورة الدفع رقم INV-{$invoice->id} من جو سبا. شكراً لاختيارك لنا!";
                    $sentInvoice = $whatsAppService->sendDocument(
                        phone:           (string) $buyerPhone,
                        fileUrlOrBase64: (string) $invoiceDocument,
                        filename:        $invoiceFilename,
                        caption:         $caption,
                    );
                }

                Log::info('Invoice PDF WhatsApp sent from Odoo response.', [
                    'invoice_id'       => $invoice->id,
                    'buyer_phone'      => $buyerPhone,
                    'used_template'    => $whatsAppService->shouldUseInvoicePdfTemplate(),
                    'template_name'    => $whatsAppService->resolveInvoicePdfTemplateName(),
                    'sent'             => $sentInvoice,
                ]);
            } else {
                Log::warning('Invoice PDF sending skipped: missing base64 or buyer phone.', [
                    'invoice_id' => $invoice->id,
                    'has_pdf'    => filled($invoiceDocument),
                    'has_phone'  => filled($buyerPhone),
                    'response_keys' => array_keys($data),
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

    private function sanitizeResponseForLog(mixed $body): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

        $sanitized = [];
        foreach ($body as $key => $value) {
            if ($key === 'invoice_pdf' && is_string($value) && $value !== '') {
                $sanitized[$key] = '[BASE64_PDF_LENGTH_' . strlen($value) . '_BYTES]';
            } elseif ($key === 'gift_cards_created' && is_array($value)) {
                $sanitized[$key] = array_map(function ($item) {
                    if (is_array($item) && isset($item['pdf']) && is_string($item['pdf'])) {
                        $item['pdf'] = '[BASE64_PDF_LENGTH_' . strlen($item['pdf']) . '_BYTES]';
                    }
                    return $item;
                }, $value);
            } elseif ($key === 'gift_card_redeemed' && is_array($value)) {
                if (isset($value['pdf']) && is_string($value['pdf'])) {
                    $value['pdf'] = '[BASE64_PDF_LENGTH_' . strlen($value['pdf']) . '_BYTES]';
                }
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeResponseForLog($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Build the 6 body variables for the jospa_invoice_pdf_sa WhatsApp template.
     *
     * Variable order (must match the approved template):
     *   1. Customer name
     *   2. Invoice number  (INV-{id})
     *   3. Order type label
     *   4. Order details summary
     *   5. Branch name
     *   6. Total amount (formatted)
     *
     * @param Invoice $invoice
     * @param string  $buyerName  Already-resolved buyer display name.
     * @param array   $data       Resolved Odoo result data array.
     * @return array<int, string>
     */
    private function buildInvoiceTemplateVariables(Invoice $invoice, string $buyerName, array $data): array
    {
        // 1. Customer name
        $customerName = $buyerName !== '' ? $buyerName : 'عميلنا العزيز';

        // 2. Invoice number
        $invoiceNumber = 'INV-' . $invoice->id;

        // 3. Order type label – detect from invoice data
        $cartIds = $invoice->cart_ids;
        $giftIds = $invoice->gift_ids;
        if (is_string($cartIds)) {
            $cartIds = json_decode($cartIds, true);
        }
        if (is_string($giftIds)) {
            $giftIds = json_decode($giftIds, true);
        }
        $hasBookings  = ! empty($cartIds);
        $hasGiftCards = ! empty($giftIds);

        if ($hasBookings && $hasGiftCards) {
            $orderType = 'طلب متنوع';
        } elseif ($hasGiftCards) {
            $orderType = 'جيفت كارد';
        } else {
            $orderType = 'حجز خدمة';
        }

        // 4. Order details summary – try to read from Odoo response, otherwise format from invoice
        $orderDetails = '';
        // Look for a pre-built summary in the Odoo data
        foreach (['order_details', 'booking_details', 'details', 'summary'] as $key) {
            $val = data_get($data, $key);
            if (is_string($val) && $val !== '') {
                $orderDetails = $val;
                break;
            }
        }

        if ($orderDetails === '' && filled($invoice->created_at)) {
            try {
                $orderDetails = 'تأكيد طلبك بنجاح بتاريخ ' . \Carbon\Carbon::parse($invoice->created_at)->format('Y-m-d');
            } catch (\Throwable) {
                $orderDetails = 'تم تأكيد طلبك بنجاح';
            }
        }

        if ($orderDetails === '') {
            $orderDetails = 'تم تأكيد طلبك بنجاح';
        }

        // Truncate to keep within template variable limit
        if (mb_strlen($orderDetails) > 250) {
            $orderDetails = mb_substr($orderDetails, 0, 247) . '...';
        }

        // 5. Branch – try Odoo data then leave generic
        $branch = '';
        foreach (['branch', 'branch_name', 'location', 'center'] as $key) {
            $val = data_get($data, $key);
            if (is_string($val) && $val !== '') {
                $branch = $val;
                break;
            }
        }
        if ($branch === '') {
            $branch = 'جو سبا';
        }

        // 6. Amount
        $amount = number_format((float) ($invoice->final_total ?? 0), 2, '.', '');

        return [
            $customerName,
            $invoiceNumber,
            $orderType,
            $orderDetails,
            $branch,
            $amount,
        ];
    }
}
