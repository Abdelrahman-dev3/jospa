<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

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
    public function sendGift($phone, $name , $to , $ref = null)
    {
        if ($to == 'sender') {
            $message = setting('taqnyat_sender');
            $message = $this->replaceVariables($message, [
                'sender_name' => $name,
                'sender_phone' => $phone,
            ]);
            return $this->sendSms($phone, $message);
        }
        if ($to == 'recipient') {
            $message = setting('taqnyat_recipient');
            $message = $this->replaceVariables($message, [
                'recipient_name' => $name,
                'recipient_phone' => $phone,
                'ref' => $ref,
            ]);
            return $this->sendSms($phone, $message);
        }
        return false;
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
        $phone = preg_replace('/[^0-9]/', '', $phone);
    
        if (preg_match('/^(966)/', $phone)) {
            $phone = '0' . substr($phone, 3);
        }
    
        if (preg_match('/^5[0-9]{8}$/', $phone)) {
            $phone = '0' . $phone;
        }
    
        if (preg_match('/^05[0-9]{8}$/', $phone)) {
            return $phone;
        }
    
        return false;
    }

}