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

                // Odoo returns JSON-RPC format: { "result": { ... } }
                // Resolve the actual result data so we can inspect PDF fields.
                $resultData = $this->resolveOdooResultData(is_array($body) ? $body : []);
                $invoiceDocument = $this->extractInvoiceDocument($resultData);
                $giftCardsCreated = $this->extractGiftCardsCreated($resultData);
                $giftCardRedeemed = $this->extractGiftCardRedeemed($resultData);

                Log::info('Odoo booking sync response received.', [
                    'invoice_id'              => $invoiceId,
                    'status'                  => $response->status(),
                    'has_invoice_pdf'         => filled($invoiceDocument),
                    'invoice_pdf_bytes'       => is_string($invoiceDocument) ? strlen($invoiceDocument) : 0,
                    'gift_cards_created_count'=> count($giftCardsCreated),
                    'has_gift_card_redeemed'  => filled($giftCardRedeemed),
                    'response_body'           => $this->sanitizeResponseForLog($body),
                ]);

                if (is_array($body) && $this->odooResponseWasRejected($body, $resultData)) {
                    $errorMsg = $this->extractErrorMessage($response);
                    Log::error('Odoo booking sync rejected by Odoo response.', [
                        'invoice_id' => $invoiceId,
                        'response'   => $this->sanitizeResponseForLog($body),
                    ]);
                    throw new \RuntimeException($errorMsg);
                }

                // Dispatch WhatsApp PDF delivery (invoice PDF + gift-card PDFs from Odoo).
                // Pass the invoice model and the resolved result data so the handler
                // can use extractInvoiceDocument() / extractGiftCardsCreated() etc.
                if ($invoice) {
                    $this->handleOdooPaymentResponse($invoice, $body);
                }

                Log::info('Odoo booking sync completed.', [
                    'invoice_id' => $invoiceId,
                    'status'     => $response->status(),
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

        $payload = [
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

        if ($invoice->gift_code && $invoice->gift_amount > 0) {
            $payload['gift_card'] = [
                'code' => $invoice->gift_code,
                'amount' => (float) $invoice->gift_amount,
            ];
        }

        return $payload;
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
            foreach ([$body, $this->resolveOdooResultData($body)] as $candidate) {
                foreach (['message', 'error', 'reason', 'detail'] as $key) {
                    $value = data_get($candidate, $key);
                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                }

                foreach (['error.message', 'error.data.message'] as $key) {
                    $value = data_get($candidate, $key);
                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                }
            }
        }

        $rawBody = trim($response->body());
        if ($rawBody !== '') {
            return $rawBody;
        }

        return 'Failed to synchronize invoice with Odoo.';
    }

    private function odooResponseWasRejected(array $responseBody, array $resultData): bool
    {
        foreach ([$responseBody, $resultData] as $candidate) {
            if (array_key_exists('status', $candidate)) {
                $status = $candidate['status'];
                if ($status === false || in_array(strtolower((string) $status), ['failed', 'error'], true)) {
                    return true;
                }
            }

            if (array_key_exists('success', $candidate) && $candidate['success'] === false) {
                return true;
            }

            if (array_key_exists('valid', $candidate) && $candidate['valid'] === false) {
                return true;
            }

            if (! empty($candidate['error'])) {
                return true;
            }
        }

        return false;
    }

    private function resolveOdooResultData(array $responseBody): array
    {
        foreach (['result', 'data'] as $key) {
            $candidate = $this->decodeOdooArrayValue(data_get($responseBody, $key));
            if ($candidate === null) {
                continue;
            }

            $nestedData = $this->decodeOdooArrayValue(data_get($candidate, 'data'));

            return $nestedData === null
                ? $candidate
                : array_replace($candidate, $nestedData);
        }

        return $responseBody;
    }

    private function extractInvoiceDocument(array $data): ?string
    {
        foreach ([
            'invoice_pdf',
            'invoice_pdf_base64',
            'invoice_document',
            'invoice_document_base64',
            'invoice_pdf_url',
            'invoice_url',
            'invoice.pdf',
            'invoice.pdf_base64',
            'invoice.document',
            'invoice.document_base64',
            'invoice.url',
            'pdf',
        ] as $key) {
            $value = data_get($data, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function extractGiftCardDocument(array $giftItem): ?string
    {
        foreach ([
            'pdf',
            'pdf_base64',
            'pdf_url',
            'url',
            'file_url',
            'download_url',
            'attachment',
            'attachment_url',
            'pdf_file',
            'card_pdf',
            'card_pdf_base64',
            'card_pdf_url',
            'gift_card_pdf',
            'gift_card_pdf_base64',
            'gift_card_pdf_url',
            'giftcard_pdf',
            'giftcard_pdf_base64',
            'giftcard_pdf_url',
            'document',
            'document_base64',
            'document_url',
        ] as $key) {
            $value = data_get($giftItem, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function resolvePublicPdfUrl(string $fileUrlOrBase64, string $filename): ?string
    {
        $fileUrlOrBase64 = trim($fileUrlOrBase64);

        if ($fileUrlOrBase64 === '') {
            return null;
        }

        if (str_starts_with($fileUrlOrBase64, 'http://') || str_starts_with($fileUrlOrBase64, 'https://')) {
            return $fileUrlOrBase64;
        }

        $base64Data = preg_replace('#^data:application/[^;]+;base64,#i', '', $fileUrlOrBase64);
        $base64Data = str_replace([' ', "\r", "\n"], '', (string) $base64Data);

        return $this->saveBase64PdfToPublicStorage($base64Data, $filename);
    }

    private function saveBase64PdfToPublicStorage(string $base64Data, string $filename): ?string
    {
        try {
            $decoded = base64_decode($base64Data, true);
            if ($decoded === false) {
                return null;
            }

            $sanitizedFilename = $this->normalizePdfFilename($filename);
            $directory = public_path('media/pdfs');

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($directory . '/' . $sanitizedFilename, $decoded);

            return url('media/pdfs/' . $sanitizedFilename);
        } catch (\Throwable $e) {
            Log::warning('Failed to save Odoo gift card PDF to public storage.', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizePdfFilename(string $filename): string
    {
        $normalizedFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', trim($filename));
        $normalizedFilename = is_string($normalizedFilename) && $normalizedFilename !== ''
            ? $normalizedFilename
            : 'gift-card.pdf';

        return str_ends_with(strtolower($normalizedFilename), '.pdf')
            ? $normalizedFilename
            : $normalizedFilename . '.pdf';
    }

    private function extractGiftCardsCreated(array $data): array
    {
        foreach ([
            'gift_cards_created',
            'gift_cards',
            'giftcards',
            'created_gifts',
            'created_gift_cards',
            'giftcards_created',
            'gift_card_pdfs',
            'gift_cards_data',
            'created_gift_cards',
            'gift_card_created',
        ] as $key) {
            $giftCards = $this->decodeOdooArrayValue(data_get($data, $key));
            if ($giftCards !== null) {
                $giftCards = $this->normalizeOdooList($giftCards);

                if ($this->giftCardListHasDocument($giftCards)) {
                    return $giftCards;
                }
            }
        }

        return $this->findGiftCardDocumentsRecursively($data);
    }

    private function giftCardListHasDocument(array $giftCards): bool
    {
        foreach ($giftCards as $giftCard) {
            if (is_array($giftCard) && $this->extractGiftCardDocument($giftCard) !== null) {
                return true;
            }
        }

        return false;
    }

    private function findGiftCardDocumentsRecursively(array $data, array $path = []): array
    {
        $items = [];

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $nextPath = array_merge($path, [(string) $key]);
            $pathText = strtolower(implode('.', $nextPath));

            if (str_contains($pathText, 'redeem') || str_contains($pathText, 'used')) {
                continue;
            }

            if (
                str_contains($pathText, 'gift')
                && $this->extractGiftCardDocument($value) !== null
                && ! filled(data_get($value, 'invoice_pdf'))
            ) {
                $items[] = $value;
            }

            $items = array_merge($items, $this->findGiftCardDocumentsRecursively($value, $nextPath));
        }

        return $items;
    }

    private function extractGiftCardRedeemed(array $data): ?array
    {
        foreach ([
            'gift_card_redeemed',
            'redeemed_gift_card',
            'gift_card_used',
        ] as $key) {
            $giftCard = $this->decodeOdooArrayValue(data_get($data, $key));
            if ($giftCard !== null) {
                return $giftCard;
            }
        }

        return null;
    }

    private function decodeOdooArrayValue(mixed $value): ?array
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

    private function normalizeInvoiceIds(mixed $ids): array
    {
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : [];
        }

        return array_values(array_filter(array_map('intval', (array) ($ids ?? [])), fn ($id) => $id > 0));
    }

    private function normalizeOdooList(array $value): array
    {
        if ($value === []) {
            return [];
        }

        return array_keys($value) === range(0, count($value) - 1)
            ? $value
            : [$value];
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

            // 2. Send Gift Card PDFs only for gift cards bought in this invoice.
            $giftIds = $this->normalizeInvoiceIds($invoice->gift_ids);
            if ($giftIds === []) {
                $ignoredGiftCardsCreated = $this->extractGiftCardsCreated($data);
                if (! empty($ignoredGiftCardsCreated)) {
                    Log::warning('Odoo returned gift card PDFs for a non-gift-card invoice; delivery skipped.', [
                        'invoice_id' => $invoice->id,
                        'gift_cards_created_count' => count($ignoredGiftCardsCreated),
                    ]);
                }
            } else {
                $giftCardsCreated = $this->extractGiftCardsCreated($data);
            }

            if (! empty($giftIds) && is_array($giftCardsCreated ?? null) && ! empty($giftCardsCreated)) {

                $localGiftCards = GiftCard::whereIn('id', $giftIds)->get()->keyBy('id');

                foreach ($giftCardsCreated as $index => $giftItem) {
                    if (! is_array($giftItem)) {
                        continue;
                    }

                    $giftDocument = $this->extractGiftCardDocument($giftItem);
                    $code = $giftItem['code'] ?? null;
                    $amount = $giftItem['amount'] ?? 0;
                    $isGift = ! empty($giftItem['is_gift']);

                    if (blank($giftDocument)) {
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

                    if (! $matchingGiftCard) {
                        Log::warning('Gift card PDF delivery skipped: no matching local gift card for Odoo item.', [
                            'invoice_id' => $invoice->id,
                            'index' => $index,
                            'code' => $code,
                        ]);
                        continue;
                    }

                    $giftCardFilename = "GiftCard_" . ($code ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $code) : ($index + 1)) . ".pdf";
                    $giftPdfUrl = $this->resolvePublicPdfUrl((string) $giftDocument, $giftCardFilename);

                    if ($matchingGiftCard && filled($giftPdfUrl)) {
                        try {
                            $matchingGiftCard->forceFill([
                                'pdf_url' => $giftPdfUrl,
                            ])->save();
                            $matchingGiftCard->pdf_url = $giftPdfUrl;

                            Log::info('Gift card PDF URL saved from Odoo response.', [
                                'invoice_id' => $invoice->id,
                                'gift_card_id' => $matchingGiftCard->id,
                                'code' => $code,
                                'pdf_url' => $giftPdfUrl,
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('Gift card PDF URL could not be saved for SMS delivery.', [
                                'invoice_id' => $invoice->id,
                                'gift_card_id' => $matchingGiftCard->id,
                                'code' => $code,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } elseif (blank($giftPdfUrl)) {
                        Log::warning('Gift card PDF URL could not be resolved for SMS delivery.', [
                            'invoice_id' => $invoice->id,
                            'gift_card_id' => $matchingGiftCard?->id,
                            'code' => $code,
                        ]);
                    }

                    $recipientPhone = $matchingGiftCard?->recipient_phone;
                    $recipientName = $matchingGiftCard?->recipient_name ?? 'عزيزنا/عزيزتنا';
                    $senderName = $matchingGiftCard?->sender_name ?? $buyerName;
                    $personalMessage = trim((string) ($matchingGiftCard?->message ?? ''));

                    // Send to Recipient (المهدي اليه) phone, fallback to sender/buyer phone if recipient phone not set
                    $targetPhone = filled($recipientPhone) ? $recipientPhone : ($matchingGiftCard?->sender_phone ?: $buyerPhone);

                    if (blank($targetPhone)) {
                        Log::warning('Gift card PDF delivery skipped: target phone number missing.', [
                            'invoice_id' => $invoice->id,
                            'code' => $code,
                            'gift_card_id' => $matchingGiftCard?->id,
                        ]);
                        continue;
                    }

                    if (filled($giftPdfUrl)) {
                        $sentSms = app(TaqnyatSmsService::class)->sendGift(
                            (string) $targetPhone,
                            [
                                'sender_name' => $senderName,
                                'sender_phone' => $matchingGiftCard?->sender_phone ?: $buyerPhone,
                                'recipient_name' => $recipientName,
                                'recipient_phone' => $targetPhone,
                                'gift_message' => $personalMessage,
                                'ref' => $code ?: $matchingGiftCard?->ref,
                                'pdf_url' => $giftPdfUrl,
                                'gift_pdf_url' => $giftPdfUrl,
                            ],
                            'recipient'
                        );

                        Log::info('Gift card PDF SMS sent from Odoo response.', [
                            'invoice_id' => $invoice->id,
                            'gift_card_id' => $matchingGiftCard?->id,
                            'code' => $code,
                            'target_phone' => $targetPhone,
                            'pdf_url' => $giftPdfUrl,
                            'sent' => (bool) $sentSms,
                        ]);
                    }

                    $giftCaption = "مرحباً {$recipientName}، لقد تلقيت بطاقة إهداء من {$senderName} من جو سبا (JO SPA)!";
                    if ($personalMessage !== '') {
                        $giftCaption .= "\nالرسالة المرفقة: \"{$personalMessage}\"";
                    }
                    $giftCaption .= "\nمرفق لكم بطاقة الإهداء الخاصة بكم.";

                    $giftTemplateName = $whatsAppService->resolveGiftCardPdfTemplateName();
                    $giftTemplateVariables = $this->buildGiftCardPdfTemplateVariables(
                        $matchingGiftCard,
                        $senderName,
                        $code,
                        $personalMessage
                    );

                    $sentGift = $giftTemplateName !== ''
                        ? $whatsAppService->sendTemplateWithDocument(
                            phone: (string) $targetPhone,
                            fileUrlOrBase64: (string) ($giftPdfUrl ?: $giftDocument),
                            filename: $giftCardFilename,
                            variables: $giftTemplateVariables,
                            templateName: $giftTemplateName,
                            fallbackToPlainDocument: false,
                        )
                        : false;

                    if (! $sentGift) {
                        Log::warning('Gift card PDF WhatsApp template send failed or is not configured.', [
                            'invoice_id' => $invoice->id,
                            'code' => $code,
                            'template_name' => $giftTemplateName,
                            'target_phone' => $targetPhone,
                        ]);
                    }

                    Log::info('Gift card PDF WhatsApp sent from Odoo response.', [
                        'invoice_id' => $invoice->id,
                        'code' => $code,
                        'recipient_name' => $recipientName,
                        'target_phone' => $targetPhone,
                        'template_name' => $giftTemplateName,
                        'sent' => $sentGift,
                    ]);
                }
            }

            // 3. Send Redeemed Gift Card PDF (if present) to the Buyer (اللي دفع)
            $giftCardRedeemed = $this->extractGiftCardRedeemed($data);
            if (is_array($giftCardRedeemed) && filled($giftCardRedeemed['pdf'] ?? null) && filled($buyerPhone)) {
                $redeemedPdfBase64 = $giftCardRedeemed['pdf'];
                $redeemedCode = $giftCardRedeemed['code'] ?? 'GC';
                $remainingBalance = $giftCardRedeemed['remaining_balance'] ?? 0;

                $redeemedFilename = "Redeemed_GiftCard_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $redeemedCode) . ".pdf";
                $redeemedCaption = "مرحباً {$buyerName}، تم استخدام بطاقة الهدايا ({$redeemedCode}) بنجاح.\nالرصيد المتبقي في البطاقة: {$remainingBalance} ر.س.\nمرفق لكم تفاصيل البطاقة والمبلغ المتبقي.";

                $redeemedTemplateName = $whatsAppService->resolveGiftCardPdfTemplateName();
                $redeemedTemplateVariables = [
                    $buyerName,
                    (string) $buyerPhone,
                    (string) $redeemedCode,
                    'Remaining balance: ' . number_format((float) $remainingBalance, 2, '.', ''),
                ];

                $sentRedeemed = $redeemedTemplateName !== ''
                    ? $whatsAppService->sendTemplateWithDocument(
                        phone: (string) $buyerPhone,
                        fileUrlOrBase64: (string) $redeemedPdfBase64,
                        filename: $redeemedFilename,
                        variables: $redeemedTemplateVariables,
                        templateName: $redeemedTemplateName,
                        fallbackToPlainDocument: false,
                    )
                    : false;

                if (! $sentRedeemed) {
                    Log::warning('Redeemed gift card PDF WhatsApp template send failed or is not configured.', [
                        'invoice_id' => $invoice->id,
                        'code' => $redeemedCode,
                        'template_name' => $redeemedTemplateName,
                        'buyer_phone' => $buyerPhone,
                    ]);
                }

                Log::info('Redeemed gift card PDF WhatsApp sent from Odoo response.', [
                    'invoice_id' => $invoice->id,
                    'code' => $redeemedCode,
                    'remaining_balance' => $remainingBalance,
                    'buyer_phone' => $buyerPhone,
                    'template_name' => $redeemedTemplateName,
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

    private function buildGiftCardPdfTemplateVariables(?GiftCard $giftCard, string $senderName, ?string $code, string $personalMessage): array
    {
        return [
            $this->fallbackTemplateValue($giftCard?->sender_name ?? $senderName, $senderName !== '' ? $senderName : '-'),
            $this->fallbackTemplateValue($giftCard?->sender_phone ?? null, '-'),
            $this->fallbackTemplateValue($code ?: $giftCard?->ref, '-'),
            $personalMessage !== '' ? $personalMessage : '-',
        ];
    }

    private function fallbackTemplateValue(mixed $value, string $fallback): string
    {
        $normalizedValue = trim((string) $value);

        return $normalizedValue !== '' ? $normalizedValue : $fallback;
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
