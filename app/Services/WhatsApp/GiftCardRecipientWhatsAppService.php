<?php

namespace App\Services\WhatsApp;

use App\Models\GiftCard;
use Illuminate\Support\Facades\Log;

class GiftCardRecipientWhatsAppService
{
    public function __construct(
        private readonly JavnaWhatsAppService $whatsAppService
    ) {
    }

    public function send(GiftCard $giftCard): bool
    {
        if (! $this->whatsAppService->isEnabled()) {
            Log::warning('Skipping gift card recipient WhatsApp because the service is disabled.', [
                'gift_card_id' => $giftCard->id,
            ]);

            return false;
        }

        if (blank($giftCard->recipient_phone)) {
            Log::warning('Skipping gift card recipient WhatsApp because the recipient phone is missing.', [
                'gift_card_id' => $giftCard->id,
            ]);

            return false;
        }

        $message = $this->buildMessage($giftCard);
        if (blank(trim($message))) {
            Log::warning('Skipping gift card recipient WhatsApp because the generated message is empty.', [
                'gift_card_id' => $giftCard->id,
            ]);

            return false;
        }

        $sent = $this->whatsAppService->sendText((string) $giftCard->recipient_phone, $message);

        if (! $sent) {
            Log::error('Gift card recipient WhatsApp send failed.', [
                'gift_card_id' => $giftCard->id,
                'phone' => $giftCard->recipient_phone,
            ]);

            return false;
        }

        Log::info('Gift card recipient WhatsApp request accepted by provider.', [
            'gift_card_id' => $giftCard->id,
            'phone' => $giftCard->recipient_phone,
        ]);

        return true;
    }

    private function buildMessage(GiftCard $giftCard): string
    {
        $senderName = trim((string) $giftCard->sender_name);
        $personalMessage = trim((string) $giftCard->message);
        $reference = trim((string) $giftCard->ref);
        $amount = $this->formatAmount((float) ($giftCard->subtotal ?? $giftCard->options_amount ?? 0));
        $appName = $this->resolveAppName();
        $domain = $this->resolveDomain();

        if ($this->isElectronicCard($giftCard)) {
            $lines = [
                $senderName !== ''
                    ? "لقد تلقيت من {$senderName} بطاقة إهداء من {$appName} بقيمة {$amount} ر.س."
                    : "لقد تلقيت بطاقة إهداء من {$appName} بقيمة {$amount} ر.س.",
            ];

            if ($reference !== '') {
                $lines[] = "الرقم المرجعي لبطاقتك هو: {$reference}";
                $lines[] = "يمكنك استخدام هذا الرقم المرجعي عند الحجز من خلال الموقع الإلكتروني الخاص بـ {$appName}:";
                $lines[] = $domain;
            }
        } else {
            $details = $this->buildGiftDetails($giftCard);
            $lines = [
                $senderName !== ''
                    ? "لقد أرسل لك {$senderName} هدية من {$appName}."
                    : "لقد أرسلنا لك هدية من {$appName}.",
            ];

            if (! empty($details)) {
                $lines[] = 'تفاصيل الهدية:';
                $lines[] = implode("\n", array_map(fn ($detail) => "- {$detail}", $details));
            } elseif ($amount !== null) {
                $lines[] = "قيمة الهدية: {$amount} ر.س.";
            }

            $lines[] = 'يمكنك الاستفادة منها من خلال زيارة أقرب فرع لجوسبا';
        }

        if ($personalMessage !== '') {
            $lines[] = "رسالة مرفقة: {$personalMessage}";
        }

        $lines[] = 'نتمنى لك تجربة جميلة.';

        return implode("\n", $lines);
    }

    private function formatAmount(float $amount): ?string
    {
        if ($amount <= 0) {
            return null;
        }

        return number_format($amount, 2, '.', '');
    }

    private function resolveAppName(): string
    {
        $appName = trim((string) setting('app_name'));

        return $appName !== '' ? $appName : 'JOSPA';
    }

    private function resolveDomain(): string
    {
        $domain = trim((string) config('app.url'));

        return $domain !== '' ? rtrim($domain, '/') : url('/');
    }

    private function isElectronicCard(GiftCard $giftCard): bool
    {
        return in_array($giftCard->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true);
    }

    private function buildGiftDetails(GiftCard $giftCard): array
    {
        $details = [];

        $services = $giftCard->services_list
            ->map(fn ($service) => trim((string) ($service->name ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($services)) {
            $details[] = 'الخدمات: ' . implode('، ', $services);
        }

        $packages = $giftCard->packages
            ->map(fn ($package) => trim((string) ($package->name ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($packages)) {
            $details[] = 'الباقات: ' . implode('، ', $packages);
        }

        $coupons = collect($giftCard->coupons ?? [])
            ->map(function ($coupon) {
                if (! is_array($coupon)) {
                    return null;
                }

                $name = trim((string) ($coupon['name'] ?? ''));
                if ($name === '') {
                    return null;
                }

                $price = isset($coupon['price']) ? (float) $coupon['price'] : null;

                return $price && $price > 0
                    ? "{$name} ({$this->formatAmount($price)} ر.س)"
                    : $name;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($coupons)) {
            $details[] = 'الكوبونات: ' . implode('، ', $coupons);
        }

        return $details;
    }
}
