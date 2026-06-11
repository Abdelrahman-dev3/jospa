<?php

namespace App\Services\WhatsApp;

use App\Services\TaqnyatSmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JavnaWhatsAppService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.javna.whatsapp_enabled')
            && filled(config('services.javna.whatsapp_api_url'))
            && filled(config('services.javna.whatsapp_api_token'));
    }

    public function sendText(string $phone, string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $normalizedPhone = $this->normalizePhoneForWhatsApp($phone);
        if ($normalizedPhone === null) {
            Log::warning('WhatsApp message skipped because the phone number is invalid.', [
                'phone' => $phone,
            ]);

            return false;
        }

        $apiUrl = (string) config('services.javna.whatsapp_api_url');
        $apiToken = (string) config('services.javna.whatsapp_api_token');
        $timeout = (int) config('services.javna.whatsapp_timeout', 15);

        foreach ($this->buildPayloadCandidates($normalizedPhone, $message) as $style => $payload) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout($timeout)
                    ->withToken($apiToken)
                    ->post($apiUrl, $payload);

                if ($response->successful()) {
                    return true;
                }

                Log::warning('WhatsApp provider rejected outbound message payload.', [
                    'style' => $style,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $exception) {
                Log::error('Failed to send outbound WhatsApp message.', [
                    'style' => $style,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return false;
    }

    private function buildPayloadCandidates(string $phone, string $message): array
    {
        $sender = trim((string) config('services.javna.whatsapp_sender', ''));
        $channelId = trim((string) config('services.javna.whatsapp_channel_id', ''));
        $preferredStyle = trim((string) config('services.javna.whatsapp_payload_style', 'auto'));

        $candidates = [
            'meta' => array_filter([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
                'from' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'simple_body' => array_filter([
                'to' => $phone,
                'body' => $message,
                'sender' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'simple_message' => array_filter([
                'to' => $phone,
                'message' => $message,
                'sender' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
        ];

        if ($preferredStyle !== '' && $preferredStyle !== 'auto' && isset($candidates[$preferredStyle])) {
            return [$preferredStyle => $candidates[$preferredStyle]];
        }

        return $candidates;
    }

    private function normalizePhoneForWhatsApp(string $phone): ?string
    {
        $validated = app(TaqnyatSmsService::class)->validatePhoneNumber($phone);
        if (! $validated) {
            return null;
        }

        return '966' . substr($validated, 1);
    }
}
