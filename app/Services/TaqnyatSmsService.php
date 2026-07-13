<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Support\SaudiPhoneNumber;

class TaqnyatSmsService
{
    protected $apiKey;
    protected $sender;
    protected $baseUrl = 'https://api.taqnyat.sa/v1';

    public function __construct()
    {
        $this->apiKey = setting('taqnyat_api_key');
        $this->sender = setting('taqnyat_sender');
    }

    /**
     * إرسال رسالة SMS
     */
    public function sendSms($recipients, $message, $sender = 'JO SPA')
    { 
        if (!setting('is_taqnyat_sms')) {
            return false;
        }

        if (empty($this->apiKey)) {
            return false;
        }
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'recipients' => is_array($recipients) ? $recipients : [$recipients],
                'body' => $message,
                'sender' => $sender ?: $this->sender,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                return $result;
            } else {
                        Log::error('Taqnyat SMS Failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * إرسال رسالة ترحيب عند التسجيل
     */
    public function sendWelcomeMessage($phone, $name)
    {
        $message = setting('taqnyat_welcome_message');
        $message = $this->replaceVariables($message, [
            'name' => $name,
            'app_name' => setting('app_name')
        ]);

        return $this->sendSms($phone, $message);
    }

    /**
     * إرسال رسالة عند إنشاء حجز
     */
    public function sendBookingCreatedMessage($phone, $bookingData)
    {
        $message = setting('taqnyat_booking_created');
        $message = $this->replaceVariables($message, [
            'booking_id' => $bookingData['booking_id'] ?? '',
            'booking_date' => $bookingData['booking_date'] ?? '',
            'booking_time' => $bookingData['booking_time'] ?? ''
        ]);

        return $this->sendSms($phone, $message);
    }

    /**
     * إرسال رسالة عند إلغاء الحجز
     */
    public function sendBookingCancelledMessage($phone, $bookingData)
    {
        $message = setting('taqnyat_booking_cancelled');
        $message = $this->replaceVariables($message, [
            'booking_id' => $bookingData['booking_id'] ?? ''
        ]);

        return $this->sendSms($phone, $message);
    }
    /**
     * إرسال هدية
     */
    public function sendGift($phone, $payload, $to, $ref = null)
    {
        $message = $this->resolveGiftTemplate($to);

        if (blank($message)) {
            return false;
        }

        $variables = [
            'sender_name' => '',
            'sender_phone' => '',
            'recipient_name' => '',
            'recipient_phone' => '',
            'ref' => $ref,
        ];

        if (is_array($payload)) {
            $variables = array_merge($variables, $payload);
        } elseif ($to === 'sender') {
            $variables['sender_name'] = $payload;
            $variables['sender_phone'] = $phone;
        } elseif ($to === 'recipient') {
            $variables['recipient_name'] = $payload;
            $variables['recipient_phone'] = $phone;
        }

        $message = $this->replaceVariables($message, $variables);

        return $this->sendSms($phone, $message);
    }

    protected function resolveGiftTemplate(string $to): ?string
    {
        if ($to === 'sender') {
            return setting(
                'taqnyat_gift_sender_message',
                'تم إرسال هديتك إلى [[recipient_name]] على الرقم [[recipient_phone]].'
            );
        }

        if ($to !== 'recipient') {
            return null;
        }

        $template = trim((string) setting('taqnyat_gift_recipient_message', ''));
        if ($template !== '') {
            return $template;
        }

        $legacyTemplate = trim((string) setting('taqnyat_recipient', ''));
        if (
            $legacyTemplate !== ''
            && (
                str_contains($legacyTemplate, '[[sender_name]]')
                || str_contains($legacyTemplate, '[[sender_phone]]')
            )
        ) {
            return $legacyTemplate;
        }

        return 'لقد تلقيت هدية من [[sender_name]]. الرقم المرجعي للحصول على الهدية من الموقع: [[ref]]';
    }

    /**
     * استبدال المتغيرات في الرسالة
     */
    protected function replaceVariables($message, $variables)
    {
        foreach ($variables as $key => $value) {
            $message = str_replace("[[{$key}]]", $value, $message);
        }
        return $message;
    }

    /**
     * التحقق من صحة رقم الهاتف
     */
    public function validatePhoneNumber($phone)
    {
        return SaudiPhoneNumber::normalize($phone) ?: false;
    }

}
