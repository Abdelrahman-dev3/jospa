<?php

namespace App\Services;

use App\Support\SaudiPhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TaqnyatSmsService
{
    protected $apiKey;
    protected $sender;
    protected $baseUrl = 'https://api.taqnyat.sa/v1';

    public function __construct()
    {
        try {
            $this->apiKey = setting('taqnyat_api_key') ?: env('TAQNYAT_API_KEY');
            $this->sender = setting('taqnyat_sender') ?: env('TAQNYAT_SENDER_NAME', 'JO SPA');
        } catch (\Throwable $e) {
            $this->apiKey = env('TAQNYAT_API_KEY');
            $this->sender = env('TAQNYAT_SENDER_NAME', 'JO SPA');
        }
    }

    public function sendSms($recipients, $message, $sender = null)
    {
        $sender = $sender ?: ($this->sender ?: 'JO SPA');
        try {
            $settingEnabled = setting('is_taqnyat_sms');
        } catch (\Throwable $e) {
            $settingEnabled = null;
        }
        $isEnabled = $settingEnabled !== null ? (bool) $settingEnabled : (!empty($this->apiKey));

        if (! $isEnabled) {
            Log::warning('Taqnyat SMS: is_taqnyat_sms setting is disabled. SMS not sent.', [
                'recipients' => $recipients,
            ]);
            return false;
        }

        if (empty($this->apiKey)) {
            Log::warning('Taqnyat SMS: API key is empty. SMS not sent.', [
                'recipients' => $recipients,
            ]);
            return false;
        }

        // Ensure sender is a valid short alphanumeric string, not a template text.
        // Some users mistakenly save the SMS template in the 'taqnyat_sender' setting.
        $finalSender = $sender ?: $this->sender;
        if (mb_strlen($finalSender) > 15 || preg_match('/[\x{0600}-\x{06FF}]/u', $finalSender)) {
            $finalSender = env('TAQNYAT_SENDER_NAME', 'JO SPA');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/messages", [
                'recipients' => is_array($recipients) ? $recipients : [$recipients],
                'body' => $message,
                'sender' => $finalSender,
            ]);

            if ($response->successful()) {
                Log::info('Taqnyat SMS: Message sent successfully.', [
                    'recipients' => $recipients,
                    'response' => $response->json(),
                ]);
                return $response->json();
            }

            Log::error('Taqnyat SMS Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'recipients' => $recipients,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Taqnyat SMS: Exception thrown while sending SMS.', [
                'recipients' => $recipients,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function sendWelcomeMessage($phone, $name)
    {
        $message = setting('taqnyat_welcome_message');
        $message = $this->replaceVariables($message, [
            'name' => $name,
            'app_name' => setting('app_name'),
        ]);

        return $this->sendSms($phone, $message);
    }

    public function sendBookingCreatedMessage($phone, $bookingData)
    {
        $message = setting('taqnyat_booking_created');
        $message = $this->replaceVariables($message, [
            'booking_id' => $bookingData['booking_id'] ?? '',
            'booking_date' => $bookingData['booking_date'] ?? '',
            'booking_time' => $bookingData['booking_time'] ?? '',
        ]);

        return $this->sendSms($phone, $message);
    }

    public function sendBookingCancelledMessage($phone, $bookingData)
    {
        $message = setting('taqnyat_booking_cancelled');
        $message = $this->replaceVariables($message, [
            'booking_id' => $bookingData['booking_id'] ?? '',
        ]);

        return $this->sendSms($phone, $message);
    }

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
            'pdf_url' => '',
            'gift_pdf_url' => '',
            'gift_message' => '',
            'gift_message_line' => '',
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

        $variables['gift_message'] = $this->sanitizeGiftMessage($variables['gift_message'] ?? '');
        $variables['gift_message_line'] = $variables['gift_message'] !== ''
            ? "\n {$variables['gift_message']}"
            : '';

        $message = $this->replaceVariables($message, $variables);

        if ($to === 'recipient') {
            $message = $this->buildRecipientGiftMessage($variables, $message);
        }

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

        return 'لقد تلقيت هدية من [[sender_name]]. الرقم المرجعي للحصول على الهدية من الموقع: [[ref]][[gift_message_line]]';
    }

    protected function replaceVariables($message, $variables)
    {
        foreach ($variables as $key => $value) {
            $message = str_replace("[[{$key}]]", $value, $message);
        }

        return $message;
    }

    protected function sanitizeGiftMessage(?string $giftMessage): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', trim((string) $giftMessage)));
    }

    protected function appendGiftMessageToRecipientMessage(string $message, ?string $giftMessage): string
    {
        $giftMessage = $this->sanitizeGiftMessage($giftMessage);

        if ($giftMessage === '' || str_contains($message, $giftMessage)) {
            return $message;
        }

        return rtrim($message) . "\n{$giftMessage}";
    }

    protected function ensureRecipientSenderDetails(string $message, ?string $senderName, ?string $senderPhone): string
    {
        $senderName = trim((string) $senderName);
        $senderPhone = trim((string) $senderPhone);
        $prefixLines = [];

        if ($senderName !== '' && !str_contains($message, $senderName)) {
            $prefixLines[] = "لقد تلقيت هدية من {$senderName}.";
        }

        if ($senderPhone !== '' && !str_contains($message, $senderPhone)) {
            $prefixLines[] = "رقم المرسل: {$senderPhone}";
        }

        if (empty($prefixLines)) {
            return $message;
        }

        return implode("\n", $prefixLines) . "\n" . ltrim($message);
    }

    protected function buildRecipientGiftMessage(array $variables, string $fallbackMessage = ''): string
    {
        $senderName = trim((string) ($variables['sender_name'] ?? ''));
        $senderPhone = trim((string) ($variables['sender_phone'] ?? ''));
        $recipientName = trim((string) ($variables['recipient_name'] ?? ''));
        $recipientPhone = trim((string) ($variables['recipient_phone'] ?? ''));
        $reference = trim((string) ($variables['ref'] ?? ''));
        $pdfUrl = trim((string) ($variables['gift_pdf_url'] ?? $variables['pdf_url'] ?? ''));
        $giftMessage = $this->sanitizeGiftMessage($variables['gift_message'] ?? '');

        $lines = [];

        /*
         * عدل شكل رسالة المستلم من هنا فقط.
         */
        if ($senderName !== '') {
            $lines[] = "لقد تلقيت هدية من {$senderName}.";
        }

        if ($senderPhone !== '') {
            $lines[] = "رقم المرسل: {$senderPhone}";
        }

        if ($reference !== '') {
            $lines[] = "الرقم المرجعي للحصول على الهدية من الموقع: {$reference}";
        }

        if ($pdfUrl !== '') {
            $lines[] = "رابط بطاقة الهدية: {$pdfUrl}";
        }

        if ($giftMessage !== '') {
            $lines[] = $giftMessage;
        }

        if (empty($lines)) {
            return $fallbackMessage;
        }

        return implode("\n", array_filter($lines, fn ($line) => trim((string) $line) !== ''));
    }

    public function validatePhoneNumber($phone)
    {
        return SaudiPhoneNumber::normalize($phone) ?: false;
    }
}
