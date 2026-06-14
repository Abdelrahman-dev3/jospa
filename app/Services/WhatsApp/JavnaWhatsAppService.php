<?php

namespace App\Services\WhatsApp;

use App\Services\TaqnyatSmsService;
use Illuminate\Http\Client\PendingRequest;
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
            Log::warning('WhatsApp sending is disabled or incomplete.', [
                'enabled' => (bool) config('services.javna.whatsapp_enabled'),
                'missing_config' => $this->missingConfigKeys(),
            ]);

            return false;
        }

        $normalizedPhone = $this->normalizePhoneForWhatsApp($phone);
        if ($normalizedPhone === null) {
            Log::warning('WhatsApp message skipped because the phone number is invalid.', [
                'original_phone' => $phone,
            ]);

            return false;
        }

        $apiUrl = (string) config('services.javna.whatsapp_api_url');
        $apiToken = (string) config('services.javna.whatsapp_api_token');
        $timeout = (int) config('services.javna.whatsapp_timeout', 15);
        $payloadCandidates = $this->buildPayloadCandidates($normalizedPhone, $message);

        Log::info('Attempting outbound WhatsApp send.', [
            'phone' => $normalizedPhone,
            'api_url' => $apiUrl,
            'timeout' => $timeout,
            'payload_styles' => array_keys($payloadCandidates),
            'message_length' => mb_strlen($message),
        ]);

        foreach ($payloadCandidates as $style => $payload) {
            foreach ($this->resolveTransportModes($style) as $transport) {
                try {
                    $response = $this->sendPayload($apiUrl, $apiToken, $timeout, $payload, $transport);

                    if ($response->successful()) {
                        Log::info('Outbound WhatsApp message sent successfully.', [
                            'style' => $style,
                            'transport' => $transport,
                            'phone' => $normalizedPhone,
                            'status' => $response->status(),
                            'body' => $this->truncateBody($response->body()),
                        ]);

                        return true;
                    }

                    Log::warning('WhatsApp provider rejected outbound message payload.', [
                        'style' => $style,
                        'transport' => $transport,
                        'phone' => $normalizedPhone,
                        'status' => $response->status(),
                        'response_body' => $this->truncateBody($response->body()),
                        'payload_keys' => array_keys($payload),
                    ]);
                } catch (\Throwable $exception) {
                    Log::error('Failed to send outbound WhatsApp message.', [
                        'style' => $style,
                        'transport' => $transport,
                        'phone' => $normalizedPhone,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        Log::error('All outbound WhatsApp payload styles failed.', [
            'phone' => $normalizedPhone,
            'payload_styles' => array_keys($payloadCandidates),
        ]);

        return false;
    }

    private function buildPayloadCandidates(string $phone, string $message): array
    {
        $sender = trim((string) config('services.javna.whatsapp_sender', ''));
        $channelId = trim((string) config('services.javna.whatsapp_channel_id', ''));
        $preferredStyle = trim((string) config('services.javna.whatsapp_payload_style', 'auto'));

        if ($sender === '') {
            Log::warning('Javna WhatsApp sender is empty. Some payload styles will fail until JAVNA_WHATSAPP_SENDER is configured.');
        }

        $candidates = [
            'javna_text' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'Text' => $message,
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_text_lower' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'text' => $message,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
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

    private function missingConfigKeys(): array
    {
        $required = [
            'services.javna.whatsapp_api_url' => 'JAVNA_WHATSAPP_API_URL',
            'services.javna.whatsapp_api_token' => 'JAVNA_WHATSAPP_API_TOKEN',
        ];

        $missing = [];
        foreach ($required as $configKey => $envKey) {
            if (! filled(config($configKey))) {
                $missing[] = $envKey;
            }
        }

        return $missing;
    }

    private function truncateBody(?string $body, int $limit = 1000): ?string
    {
        if ($body === null) {
            return null;
        }

        return mb_strlen($body) > $limit
            ? mb_substr($body, 0, $limit) . '...'
            : $body;
    }

    private function resolveTransportModes(string $style): array
    {
        if (str_starts_with($style, 'javna_')) {
            return ['json', 'form', 'multipart'];
        }

        return ['json'];
    }

    private function sendPayload(
        string $apiUrl,
        string $apiToken,
        int $timeout,
        array $payload,
        string $transport
    ) {
        $request = Http::acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'x-api-key' => $apiToken,
            ]);

        $endpoint = rtrim($apiUrl, '/') . '/whatsapp/v1.0/message/text';

        return match ($transport) {
            'form' => $request->asForm()->post($endpoint, $payload),
            'multipart' => $this->sendMultipartPayload($request, $endpoint, $payload),
            default => $request->asJson()->post($endpoint, $payload),
        };
    }

    private function sendMultipartPayload(PendingRequest $request, string $endpoint, array $payload)
    {
        $multipart = [];
        foreach ($payload as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $request->send('POST', $endpoint, [
            'multipart' => $multipart,
        ]);
    }
}
