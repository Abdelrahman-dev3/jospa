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

    public function shouldUsePaymentTemplate(): bool
    {
        return $this->isEnabled() && filled(config('services.javna.whatsapp_template_name'));
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
        $payloadCandidates = $this->buildTextPayloadCandidates($normalizedPhone, $message);

        Log::info('Attempting outbound WhatsApp send.', [
            'phone' => $normalizedPhone,
            'api_url' => $apiUrl,
            'timeout' => $timeout,
            'payload_styles' => array_keys($payloadCandidates),
            'message_length' => mb_strlen($message),
        ]);

        return $this->deliverPayloadCandidates(
            endpoint: $this->resolveTextEndpoint($apiUrl),
            apiToken: $apiToken,
            timeout: $timeout,
            phone: $normalizedPhone,
            payloadCandidates: $payloadCandidates,
            successLogMessage: 'Outbound WhatsApp message sent successfully.',
            rejectedLogMessage: 'WhatsApp provider rejected outbound message payload.',
            exceptionLogMessage: 'Failed to send outbound WhatsApp message.',
            finalErrorLogMessage: 'All outbound WhatsApp payload styles failed.'
        );
    }

    public function sendTemplate(string $phone, array $variables, ?string $templateName = null, ?string $language = null): bool
    {
        if (! $this->isEnabled()) {
            Log::warning('WhatsApp template sending is disabled or incomplete.', [
                'enabled' => (bool) config('services.javna.whatsapp_enabled'),
                'missing_config' => $this->missingConfigKeys(),
            ]);

            return false;
        }

        $normalizedPhone = $this->normalizePhoneForWhatsApp($phone);
        if ($normalizedPhone === null) {
            Log::warning('WhatsApp template skipped because the phone number is invalid.', [
                'original_phone' => $phone,
            ]);

            return false;
        }

        $resolvedTemplateName = trim((string) ($templateName ?: config('services.javna.whatsapp_template_name')));
        if ($resolvedTemplateName === '') {
            Log::warning('WhatsApp template skipped because the template name is missing.');

            return false;
        }

        $resolvedLanguage = trim((string) ($language ?: config('services.javna.whatsapp_template_language', 'ar')));
        $apiUrl = (string) config('services.javna.whatsapp_api_url');
        $apiToken = (string) config('services.javna.whatsapp_api_token');
        $timeout = (int) config('services.javna.whatsapp_timeout', 15);
        $payloadCandidates = $this->buildTemplatePayloadCandidates(
            phone: $normalizedPhone,
            templateName: $resolvedTemplateName,
            language: $resolvedLanguage,
            variables: $variables
        );

        Log::info('Attempting outbound WhatsApp template send.', [
            'phone' => $normalizedPhone,
            'api_url' => $apiUrl,
            'timeout' => $timeout,
            'template_name' => $resolvedTemplateName,
            'template_language' => $resolvedLanguage,
            'payload_styles' => array_keys($payloadCandidates),
            'variables_count' => count($variables),
        ]);

        return $this->deliverPayloadCandidates(
            endpoint: $this->resolveTemplateEndpoint($apiUrl),
            apiToken: $apiToken,
            timeout: $timeout,
            phone: $normalizedPhone,
            payloadCandidates: $payloadCandidates,
            successLogMessage: 'Outbound WhatsApp template sent successfully.',
            rejectedLogMessage: 'WhatsApp provider rejected outbound template payload.',
            exceptionLogMessage: 'Failed to send outbound WhatsApp template.',
            finalErrorLogMessage: 'All outbound WhatsApp template payload styles failed.'
        );
    }

    private function buildTextPayloadCandidates(string $phone, string $message): array
    {
        $sender = trim((string) config('services.javna.whatsapp_sender', ''));
        $channelId = trim((string) config('services.javna.whatsapp_channel_id', ''));
        $preferredStyle = trim((string) config('services.javna.whatsapp_payload_style', 'auto'));

        if ($sender === '') {
            Log::warning('Javna WhatsApp sender is empty. Some payload styles will fail until JAVNA_WHATSAPP_SENDER is configured.');
        }

        $candidates = [
            'javna_content' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'Content' => [
                    'Type' => 'text',
                    'Text' => $message,
                ],
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
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

    private function buildTemplatePayloadCandidates(string $phone, string $templateName, string $language, array $variables): array
    {
        $sender = trim((string) config('services.javna.whatsapp_sender', ''));
        $channelId = trim((string) config('services.javna.whatsapp_channel_id', ''));
        $namespace = trim((string) config('services.javna.whatsapp_template_namespace', ''));
        $preferredStyle = trim((string) config('services.javna.whatsapp_payload_style', 'auto'));
        $parameterValues = array_values(array_map(fn ($value) => (string) $value, $variables));
        $textParameters = array_map(fn ($value) => [
            'type' => 'text',
            'text' => $value,
        ], $parameterValues);

        $candidates = [
            'javna_template_messages_content' => array_filter([
                'Messages' => [[
                    'To' => $phone,
                    'From' => $sender,
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages' => array_filter([
                'Messages' => [[
                    'To' => $phone,
                    'From' => $sender,
                    'TemplateName' => $templateName,
                    'Language' => $language,
                    'Parameters' => $parameterValues,
                    'Namespace' => $namespace !== '' ? $namespace : null,
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_lower' => array_filter([
                'messages' => [[
                    'to' => $phone,
                    'from' => $sender,
                    'templateName' => $templateName,
                    'language' => $language,
                    'parameters' => $parameterValues,
                    'namespace' => $namespace !== '' ? $namespace : null,
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_content' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'Content' => array_filter([
                    'Type' => 'template',
                    'Template' => array_filter([
                        'Name' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ], fn ($value) => $value !== null && $value !== ''),
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'TemplateName' => $templateName,
                'Language' => $language,
                'Parameters' => $parameterValues,
                'ChannelId' => $channelId !== '' ? $channelId : null,
                'Namespace' => $namespace !== '' ? $namespace : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_lower' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'templateName' => $templateName,
                'language' => $language,
                'parameters' => $parameterValues,
                'channelId' => $channelId !== '' ? $channelId : null,
                'namespace' => $namespace !== '' ? $namespace : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'meta_template' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
                'type' => 'template',
                'template' => array_filter([
                    'name' => $templateName,
                    'namespace' => $namespace !== '' ? $namespace : null,
                    'language' => ['code' => $language],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => $textParameters,
                    ]],
                ], fn ($value) => $value !== null && $value !== ''),
            ], fn ($value) => $value !== null && $value !== ''),
        ];

        if ($preferredStyle !== '' && $preferredStyle !== 'auto' && isset($candidates[$preferredStyle])) {
            return [$preferredStyle => $candidates[$preferredStyle]];
        }

        return $candidates;
    }

    private function deliverPayloadCandidates(
        string $endpoint,
        string $apiToken,
        int $timeout,
        string $phone,
        array $payloadCandidates,
        string $successLogMessage,
        string $rejectedLogMessage,
        string $exceptionLogMessage,
        string $finalErrorLogMessage
    ): bool {
        foreach ($payloadCandidates as $style => $payload) {
            foreach ($this->resolveTransportModes($style) as $transport) {
                try {
                    $response = $this->sendPayload($endpoint, $apiToken, $timeout, $payload, $transport);

                    if ($response->successful()) {
                        Log::info($successLogMessage, [
                            'style' => $style,
                            'transport' => $transport,
                            'phone' => $phone,
                            'status' => $response->status(),
                            'body' => $this->truncateBody($response->body()),
                        ]);

                        return true;
                    }

                    Log::warning($rejectedLogMessage, [
                        'style' => $style,
                        'transport' => $transport,
                        'phone' => $phone,
                        'status' => $response->status(),
                        'response_body' => $this->truncateBody($response->body()),
                        'payload_keys' => array_keys($payload),
                    ]);
                } catch (\Throwable $exception) {
                    Log::error($exceptionLogMessage, [
                        'style' => $style,
                        'transport' => $transport,
                        'phone' => $phone,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        Log::error($finalErrorLogMessage, [
            'phone' => $phone,
            'payload_styles' => array_keys($payloadCandidates),
        ]);

        return false;
    }

    private function resolveTransportModes(string $style): array
    {
        if (str_starts_with($style, 'javna_template_')) {
            return ['json'];
        }

        if (str_starts_with($style, 'javna_')) {
            return ['json', 'form', 'multipart'];
        }

        return ['json'];
    }

    private function sendPayload(
        string $endpoint,
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

    private function resolveTextEndpoint(string $apiUrl): string
    {
        return rtrim($apiUrl, '/') . '/whatsapp/v1.0/message/text';
    }

    private function resolveTemplateEndpoint(string $apiUrl): string
    {
        $path = trim((string) config('services.javna.whatsapp_template_path', '/whatsapp/v1.0/message/template'));

        return rtrim($apiUrl, '/') . '/' . ltrim($path, '/');
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
}
