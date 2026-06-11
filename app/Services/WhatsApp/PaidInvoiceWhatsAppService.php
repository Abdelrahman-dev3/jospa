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
            return false;
        }

        $invoice = Invoice::with('user')->find($invoiceId);
        if (! $invoice || ! $invoice->user || blank($invoice->user->mobile)) {
            return false;
        }

        $bookings = $this->loadBookings($invoice->cart_ids ?? []);
        $giftCards = $this->loadGiftCards($invoice->gift_ids ?? []);
        $message = $this->buildMessage($invoice, $bookings, $giftCards);

        if (blank(trim($message))) {
            return false;
        }

        return $this->whatsAppService->sendText((string) $invoice->user->mobile, $message);
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
