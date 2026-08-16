<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Services\WhatsApp\GiftCardRecipientWhatsAppService;
use Illuminate\Support\Collection;

class GiftCardActivationService
{
    public function __construct(
        private readonly TaqnyatSmsService $smsService,
        private readonly GiftCardRecipientWhatsAppService $giftCardRecipientWhatsAppService
    ) {
    }

    public function activatePendingForUser(int $userId): Collection
    {
        $giftCards = GiftCard::where('user_id', $userId)->where('payment_status', 0)->get();
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

    public function sendNotifications(Collection $giftCards, bool $sendWhatsApp = true, bool $sendSms = true): void
    {
        foreach ($giftCards as $giftCard) {
            $this->sendNotificationsForGiftCard($giftCard, $sendWhatsApp, $sendSms);
        }
    }

    public function sendNotificationsForIds(array $giftIds, bool $sendWhatsApp = true, bool $sendSms = true): void
    {
        if (empty($giftIds)) {
            return;
        }

        $giftCards = GiftCard::whereIn('id', $giftIds)->get();

        $this->sendNotifications($giftCards, $sendWhatsApp, $sendSms);
    }

    private function activateCollection(Collection $giftCards): Collection
    {
        return $giftCards->map(function (GiftCard $giftCard) {
            $attributes = [
                'payment_status' => 1,
            ];

            if ($this->requiresReference($giftCard)) {
                $attributes['ref'] = $giftCard->ref ?: GiftCard::generateUniqueReference();
                $attributes['balance'] = (float) ($giftCard->subtotal ?? $giftCard->options_amount ?? 0);
            }

            $giftCard->fill($attributes);
            $giftCard->save();

            return $giftCard->fresh();
        });
    }

    private function sendNotificationsForGiftCard(GiftCard $giftCard, bool $sendWhatsApp = true, bool $sendSms = true): void
    {
        $giftCard = $giftCard->fresh() ?: $giftCard;

        if ($this->shouldNotifyRecipient($giftCard) && filled($giftCard->recipient_phone)) {
            if ($sendSms) {
                $this->smsService->sendGift(
                    $giftCard->recipient_phone,
                    [
                        'sender_name' => $giftCard->sender_name,
                        'sender_phone' => $giftCard->sender_phone,
                        'recipient_name' => $giftCard->recipient_name,
                        'recipient_phone' => $giftCard->recipient_phone,
                        'gift_message' => $giftCard->message,
                        'ref' => $giftCard->ref,
                        'pdf_url' => $giftCard->pdf_url,
                        'gift_pdf_url' => $giftCard->pdf_url,
                    ],
                    'recipient'
                );
            }

            if ($sendWhatsApp) {
                $this->giftCardRecipientWhatsAppService->send($giftCard);
            }
        }
    }

    private function shouldNotifyRecipient(GiftCard $giftCard): bool
    {
        return in_array($giftCard->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true);
    }

    private function requiresReference(GiftCard $giftCard): bool
    {
        return in_array($giftCard->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true);
    }
}
