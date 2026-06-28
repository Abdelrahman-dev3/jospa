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
        $recipientName = trim((string) $giftCard->recipient_name);
        $senderName = trim((string) $giftCard->sender_name);
        $personalMessage = trim((string) $giftCard->message);
        $reference = trim((string) $giftCard->ref);
        $amount = $this->formatAmount((float) ($giftCard->subtotal ?? $giftCard->options_amount ?? 0));
        $appName = $this->resolveAppName();

        $lines = [
            $recipientName !== '' ? "مرحبا {$recipientName}" : 'مرحبا',
            $senderName !== ''
                ? "أرسل لك {$senderName} جيفت كارد من {$appName}."
                : "وصلك جيفت كارد جديدة من {$appName}.",
        ];

        if ($amount !== null) {
            $lines[] = "قيمة الهدية: {$amount} ر.س.";
        }

        if ($reference !== '') {
            $lines[] = "كود الجيفت كارد: {$reference}.";
        }

        if ($personalMessage !== '') {
            $lines[] = "رسالة مرفقة: {$personalMessage}";
        }

        $lines[] = 'نتمنى لك تجربة جميلة ومميزة.';

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
}
