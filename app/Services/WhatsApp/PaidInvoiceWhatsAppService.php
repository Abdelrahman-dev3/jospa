<?php

namespace App\Services\WhatsApp;

use App\Models\GiftCard;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;

class PaidInvoiceWhatsAppService
{
    public function __construct(
        private readonly JavnaWhatsAppService $whatsAppService
    ) {
    }

    public function sendForInvoice(int $invoiceId): bool
    {
        if (! $this->whatsAppService->isEnabled()) {
            Log::warning('Skipping paid invoice WhatsApp because the service is disabled.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        $invoice = Invoice::with('user')->find($invoiceId);
        if (! $invoice) {
            Log::warning('Skipping paid invoice WhatsApp because the invoice was not found.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        if (! $invoice->user) {
            Log::warning('Skipping paid invoice WhatsApp because the invoice user was not found.', [
                'invoice_id' => $invoiceId,
                'user_id' => $invoice->user_id,
            ]);
            
            return false;
        }

        if (blank($invoice->user->mobile)) {
            Log::warning('Skipping paid invoice WhatsApp because the customer mobile is missing.', [
                'invoice_id' => $invoiceId,
                'user_id' => $invoice->user_id,
            ]);

            return false;
        }

        $bookingIds = $invoice->cart_ids ?? [];
        $giftIds = $invoice->gift_ids ?? [];

        if (is_string($bookingIds)) {
            $bookingIds = json_decode($bookingIds, true) ?: explode(',', $bookingIds);
        }

        if (is_string($giftIds)) {
            $giftIds = json_decode($giftIds, true) ?: explode(',', $giftIds);
        }

        $bookings = $this->loadBookings((array) $bookingIds);
        $giftCards = $this->loadGiftCards((array) $giftIds);
        $message = $this->buildMessage($invoice, $bookings, $giftCards);

        if (blank(trim($message))) {
            Log::warning('Skipping paid invoice WhatsApp because the generated message is empty.', [
                'invoice_id' => $invoiceId,
            ]);

            return false;
        }

        Log::info('Prepared paid invoice WhatsApp message.', [
            'invoice_id' => $invoiceId,
            'user_id' => $invoice->user_id,
            'phone' => $invoice->user->mobile,
            'bookings_count' => $bookings->count(),
            'gift_cards_count' => $giftCards->count(),
            'product_items_count' => $invoice->productItems->count(),
            'message_length' => mb_strlen($message),
        ]);

        $sent = $this->whatsAppService->sendText((string) $invoice->user->mobile, $message);

        if (! $sent) {
            Log::error('Paid invoice WhatsApp send failed.', [
                'invoice_id' => $invoiceId,
                'user_id' => $invoice->user_id,
                'phone' => $invoice->user->mobile,
            ]);

            return false;
        }

        Log::info('Paid invoice WhatsApp send completed successfully.', [
            'invoice_id' => $invoiceId,
            'user_id' => $invoice->user_id,
            'phone' => $invoice->user->mobile,
        ]);

        return true;
    }

    private function loadBookings(array $bookingIds): Collection
    {
        if (empty($bookingIds)) {
            return collect();
        }

        return Booking::with([
            'branch',
            'services',
            'bookingPackages',
        ])->whereIn('id', $bookingIds)->orderBy('start_date_time')->get();
    }

    private function loadGiftCards(array $giftIds): Collection
    {
        if (empty($giftIds)) {
            return collect();
        }

        return GiftCard::whereIn('id', $giftIds)->get();
    }

    private function buildMessage(Invoice $invoice, Collection $bookings, Collection $giftCards): string
    {
        $customerName = trim((string) ($invoice->user->full_name ?? $invoice->user->first_name ?? 'عميلنا العزيز'));

        $lines = [
            'مرحبا ' . $customerName,
            'تم تأكيد عملية الدفع بنجاح.',
            'رقم الفاتورة: INV-' . $invoice->id,
            'المبلغ المدفوع: ' . $this->formatAmount($invoice->final_total) . ' ر.س',
        ];

        if (filled($invoice->payment_method)) {
            $lines[] = 'طريقة الدفع: ' . $invoice->payment_method;
        }

        if ($bookings->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'تفاصيل الحجوزات:';

            foreach ($bookings as $index => $booking) {
                $bookingLines = array_filter([
                    ($index + 1) . '. حجز #' . $booking->id,
                    'الموعد: ' . $this->formatBookingDateTime($booking->start_date_time),
                    'الفرع: ' . $this->stringValue(optional($booking->branch)->name, 'غير محدد'),
                    $this->buildBookingServicesLine($booking),
                    $this->buildBookingPackagesLine($booking),
                ]);

                $lines[] = implode(' | ', $bookingLines);
            }
        }

        $productLines = $this->buildProductLines($invoice);
        if (! empty($productLines)) {
            $lines[] = '';
            $lines[] = 'المنتجات:';
            foreach ($productLines as $productLine) {
                $lines[] = '- ' . $productLine;
            }
        }

        $giftLines = $this->buildGiftCardLines($giftCards);
        if (! empty($giftLines)) {
            $lines[] = '';
            $lines[] = 'بطاقات الهدايا:';
            foreach ($giftLines as $giftLine) {
                $lines[] = '- ' . $giftLine;
            }
        }

        $lines[] = '';
        $lines[] = 'شكرا لاختياركم JO SPA';

        return implode("\n", $lines);
    }

    private function buildBookingServicesLine(Booking $booking): ?string
    {
        $serviceNames = $booking->services
            ->map(fn ($service) => $this->stringValue($service->service_name ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($serviceNames)) {
            return null;
        }

        return 'الخدمات: ' . implode('، ', $serviceNames);
    }

    private function buildBookingPackagesLine(Booking $booking): ?string
    {
        $packageNames = $booking->bookingPackages
            ->map(fn ($package) => $this->stringValue($package->name ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($packageNames)) {
            return null;
        }

        return 'الباقات: ' . implode('، ', $packageNames);
    }

    private function buildProductLines(Invoice $invoice): array
    {
        return $invoice->productItems
            ->map(function ($item) {
                $name = $this->stringValue(optional($item->product)->name, 'منتج');
                $qty = (int) ($item->qty ?? 1);

                return $name . ' x' . max($qty, 1);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function buildGiftCardLines(Collection $giftCards): array
    {
        return $giftCards->map(function (GiftCard $giftCard) {
            $parts = array_filter([
                'بطاقة #' . ($giftCard->ref ?: $giftCard->id),
                'القيمة: ' . $this->formatAmount($giftCard->subtotal ?? $giftCard->options_amount ?? 0) . ' ر.س',
                filled($giftCard->recipient_name) ? 'إلى: ' . $giftCard->recipient_name : null,
                $this->buildGiftServicesLine($giftCard),
                $this->buildGiftPackagesLine($giftCard),
            ]);

            return implode(' | ', $parts);
        })->filter()->values()->all();
    }

    private function buildGiftServicesLine(GiftCard $giftCard): ?string
    {
        try {
            $services = $giftCard->services_list
                ->map(fn ($service) => $this->stringValue($service->name ?? null))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Unable to read gift card services for WhatsApp message.', [
                'gift_card_id' => $giftCard->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (empty($services)) {
            return null;
        }

        return 'الخدمات: ' . implode('، ', $services);
    }

    private function buildGiftPackagesLine(GiftCard $giftCard): ?string
    {
        try {
            $packages = $giftCard->packages
                ->map(fn ($package) => $this->stringValue($package->name ?? null))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Unable to read gift card packages for WhatsApp message.', [
                'gift_card_id' => $giftCard->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (empty($packages)) {
            return null;
        }

        return 'الباقات: ' . implode('، ', $packages);
    }

    private function formatBookingDateTime($value): string
    {
        if (blank($value)) {
            return 'غير محدد';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $exception) {
            return (string) $value;
        }
    }

    private function stringValue(mixed $value, string $default = ''): string
    {
        if (is_array($value)) {
            foreach (['ar', 'en'] as $locale) {
                if (! empty($value[$locale])) {
                    return (string) $value[$locale];
                }
            }

            return (string) (collect($value)->filter()->first() ?? $default);
        }

        return filled($value) ? trim((string) $value) : $default;
    }

    private function formatAmount($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
