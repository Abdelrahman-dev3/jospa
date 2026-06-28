<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Services\WhatsApp\GiftCardRecipientWhatsAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GiftCardActivationService
{
    public function __construct(
        private readonly TaqnyatSmsService $smsService,
        private readonly GiftCardRecipientWhatsAppService $giftCardRecipientWhatsAppService
    ) {
    }

    public function activatePendingForUser(int $userId): Collection
    {
        $giftCards = GiftCard::where('user_id', $userId)
            ->where('payment_status', 0)
            ->get();

        return $this->activateCollection($giftCards);
    }

    public function activateByIds(array $giftIds): Collection
    {
        if (empty($giftIds)) {
            return collect();
        }

        $giftCards = GiftCard::whereIn('id', $giftIds)
            ->where('payment_status', 0)
            ->get();

        return $this->activateCollection($giftCards);
    }

    public function sendNotifications(Collection $giftCards): void
    {
        foreach ($giftCards as $giftCard) {
            $this->sendNotificationsForGiftCard($giftCard);
        }
    }

    public function sendNotificationsForIds(array $giftIds): void
    {
        if (empty($giftIds)) {
            return;
        }

        $giftCards = GiftCard::whereIn('id', $giftIds)->get();

        $this->sendNotifications($giftCards);
    }

    private function activateCollection(Collection $giftCards): Collection
    {
        return $giftCards->map(function (GiftCard $giftCard) {
            $attributes = [
                'payment_status' => 1,
            ];

            if ($this->requiresReference($giftCard)) {
                $attributes['ref'] = $giftCard->ref ?: $this->generateReference();
                $attributes['balance'] = (float) ($giftCard->subtotal ?? $giftCard->options_amount ?? 0);
            }

            $giftCard->fill($attributes);
            $giftCard->save();

            return $giftCard->fresh();
        });
    }

    private function sendNotificationsForGiftCard(GiftCard $giftCard): void
    {
        if (filled($giftCard->sender_phone)) {
            $this->smsService->sendGift($giftCard->sender_phone, $giftCard->sender_name, 'sender');
        }

        if (filled($giftCard->recipient_phone)) {
            $this->smsService->sendGift(
                $giftCard->recipient_phone,
                $giftCard->recipient_name,
                'recipient',
                $giftCard->ref
            );

            $this->giftCardRecipientWhatsAppService->send($giftCard);
        }
    }

    private function requiresReference(GiftCard $giftCard): bool
    {
        return in_array($giftCard->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true);
    }

    private function generateReference(): string
    {
        return 'GC-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}
