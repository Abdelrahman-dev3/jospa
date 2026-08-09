<?php

namespace App\Services\WhatsApp;

use App\Services\TaqnyatSmsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JavnaWhatsAppService
{
    private ?array $lastAcceptedMessage = null;

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
        $this->lastAcceptedMessage = null;

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
        $this->lastAcceptedMessage = null;

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

    public function sendDocument(string $phone, string $fileUrlOrBase64, string $filename = 'document.pdf', ?string $caption = null): bool
    {
        $this->lastAcceptedMessage = null;

        if (! $this->isEnabled()) {
            Log::warning('WhatsApp document sending is disabled or incomplete.', [
                'enabled' => (bool) config('services.javna.whatsapp_enabled'),
                'missing_config' => $this->missingConfigKeys(),
            ]);

            return false;
        }

        $normalizedPhone = $this->normalizePhoneForWhatsApp($phone);
        if ($normalizedPhone === null) {
            Log::warning('WhatsApp document skipped because the phone number is invalid.', [
                'original_phone' => $phone,
            ]);

            return false;
        }

        $apiUrl = (string) config('services.javna.whatsapp_api_url');
        $apiToken = (string) config('services.javna.whatsapp_api_token');
        $timeout = (int) config('services.javna.whatsapp_timeout', 15);

        $fileUrl = null;
        $base64Data = null;

        if (str_starts_with($fileUrlOrBase64, 'http://') || str_starts_with($fileUrlOrBase64, 'https://')) {
            $fileUrl = $fileUrlOrBase64;
        } else {
            $base64Data = preg_replace('#^data:application/\w+;base64,#i', '', trim($fileUrlOrBase64));
            $base64Data = str_replace([' ', "\r", "\n"], '', (string) $base64Data);

            $savedUrl = $this->saveBase64ToPublicStorage($base64Data, $filename);
            if ($savedUrl) {
                $fileUrl = $savedUrl;
            }
        }

        $payloadCandidates = $this->buildDocumentPayloadCandidates(
            phone: $normalizedPhone,
            fileUrl: $fileUrl,
            base64Data: $base64Data,
            filename: $filename,
            caption: $caption
        );

        Log::info('Attempting outbound WhatsApp document send.', [
            'phone' => $normalizedPhone,
            'api_url' => $apiUrl,
            'filename' => $filename,
            'file_url' => $fileUrl,
            'has_base64' => ! empty($base64Data),
            'payload_styles' => array_keys($payloadCandidates),
        ]);

        $sent = $this->deliverPayloadCandidates(
            endpoint: $this->resolveDocumentEndpoint($apiUrl),
            apiToken: $apiToken,
            timeout: $timeout,
            phone: $normalizedPhone,
            payloadCandidates: $payloadCandidates,
            successLogMessage: 'Outbound WhatsApp document sent successfully.',
            rejectedLogMessage: 'WhatsApp provider rejected outbound document payload.',
            exceptionLogMessage: 'Failed to send outbound WhatsApp document.',
            finalErrorLogMessage: 'All outbound WhatsApp document payload styles failed.'
        );

        if (! $sent && $fileUrl !== null) {
            $fallbackMessage = trim(($caption ? $caption . "\n" : '') . 'تحميل المستند: ' . $fileUrl);
            Log::info('Fallback: sending WhatsApp text with document link.', [
                'phone' => $normalizedPhone,
                'filename' => $filename,
                'url' => $fileUrl,
            ]);

            return $this->sendText($phone, $fallbackMessage);
        }

        return $sent;
    }

    public function getLastAcceptedMessage(): ?array
    {
        return $this->lastAcceptedMessage;
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
        $parameterValues = $this->normalizeTemplateVariables($variables);
        $textParameters = array_map(fn ($value) => [
            'type' => 'text',
            'text' => $value,
        ], $parameterValues);
        $localizableParameters = array_map(fn ($value) => [
            'default' => $value,
        ], $parameterValues);

        $candidates = [
            'javna_official_template_body_params' => array_filter([
                'messages' => [[
                    'from' => $sender,
                    'destinations' => [$phone],
                    'content' => array_filter([
                        'templateName' => $templateName,
                        'templateLanguage' => $language,
                        'bodyParams' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_content_template_components' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'Template' => array_filter([
                            'Name' => $templateName,
                            'Language' => [
                                'Code' => $language,
                            ],
                            'Components' => [[
                                'Type' => 'body',
                                'Parameters' => $textParameters,
                            ]],
                        ], fn ($value) => $value !== null && $value !== ''),
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_content_template_components_lower' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'type' => 'template',
                        'template' => array_filter([
                            'name' => $templateName,
                            'language' => [
                                'code' => $language,
                            ],
                            'components' => [[
                                'type' => 'body',
                                'parameters' => $textParameters,
                            ]],
                        ], fn ($value) => $value !== null && $value !== ''),
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_body_localizable_params' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_body_localizable_params_lower' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_body_LocalizableParams' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'LocalizableParams' => $localizableParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_body_localizableParams' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'localizableParams' => $localizableParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_localizable_params' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'localizable_params' => $localizableParameters,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_localizable' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_body_localizable' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_placeholders_lower' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'body' => [
                                'placeholders' => $parameterValues,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_placeholders_objects_lower' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'body' => [
                                'placeholders' => $textParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_parameters_objects' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'Parameters' => $textParameters,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_objects' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'Placeholders' => $textParameters,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_content_lower' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => [
                        'templateName' => $templateName,
                        'language' => $language,
                        'templateData' => [
                            'body' => [
                                'placeholders' => $parameterValues,
                            ],
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_content_mixed' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => [
                        'TemplateName' => $templateName,
                        'language' => $language,
                        'templateData' => [
                            'body' => [
                                'placeholders' => $parameterValues,
                            ],
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_content_language_body' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => [
                        'templateName' => $templateName,
                        'language' => $language,
                        'body' => [
                            'placeholders' => $parameterValues,
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'Placeholders' => $parameterValues,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_with_parameters' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'Parameters' => $parameterValues,
                        'TemplateData' => [
                            'Body' => [
                                'Placeholders' => $parameterValues,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_data_lower' => array_filter([
                'messages' => [[
                    'from' => $sender,
                    'destinations' => [$phone],
                    'content' => array_filter([
                        'type' => 'template',
                        'templateName' => $templateName,
                        'templateLanguage' => $language,
                        'templateData' => [
                            'body' => [
                                'placeholders' => $parameterValues,
                            ],
                        ],
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_to_content_data' => array_filter([
                'messages' => [[
                    'from' => $sender,
                    'to' => $phone,
                    'content' => [
                        'templateName' => $templateName,
                        'language' => $language,
                        'templateData' => [
                            'body' => [
                                'placeholders' => $parameterValues,
                            ],
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_Messages_To_Content_TemplateData' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'To' => $phone,
                    'Content' => [
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'Placeholders' => $parameterValues,
                            ],
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_Messages_destinations_content_templateData' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'destinations' => [[
                        'to' => $phone,
                    ]],
                    'content' => [
                        'templateName' => $templateName,
                        'language' => $language,
                        'templateData' => [
                            'body' => [
                                'placeholders' => $parameterValues,
                            ],
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_Messages_Destinations_Content_TemplateData' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [[
                        'To' => $phone,
                    ]],
                    'Content' => [
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'TemplateData' => [
                            'Body' => [
                                'Placeholders' => $parameterValues,
                            ],
                        ],
                    ],
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_content' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [[
                        'To' => $phone,
                    ]],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_content_lower_to' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [[
                        'to' => $phone,
                    ]],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_content_msisdn' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [[
                        'msisdn' => $phone,
                    ]],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_content_string' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [[
                        'To' => $phone,
                    ]],
                    'TemplateName' => $templateName,
                    'Language' => $language,
                    'Parameters' => $parameterValues,
                    'Namespace' => $namespace !== '' ? $namespace : null,
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destenations_content' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destenations' => [[
                        'To' => $phone,
                    ]],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_destenations_content_lower_to' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destenations' => [[
                        'to' => $phone,
                    ]],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'Language' => $language,
                        'Namespace' => $namespace !== '' ? $namespace : null,
                        'Parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_template_messages_lower_destinations_content' => array_filter([
                'messages' => [[
                    'from' => $sender,
                    'destinations' => [[
                        'to' => $phone,
                    ]],
                    'content' => array_filter([
                        'type' => 'template',
                        'templateName' => $templateName,
                        'language' => $language,
                        'namespace' => $namespace !== '' ? $namespace : null,
                        'parameters' => $parameterValues,
                    ], fn ($value) => $value !== null && $value !== ''),
                ]],
            ], fn ($value) => $value !== null && $value !== ''),
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

    private function normalizeTemplateVariables(array $variables): array
    {
        return array_values(array_map(function ($value) {
            $normalizedValue = trim((string) $value);

            return $normalizedValue !== '' ? $normalizedValue : '-';
        }, $variables));
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

                    if ($response->successful() && $this->responseRepresentsAcceptedSend($response)) {
                        $this->lastAcceptedMessage = $this->extractAcceptedMessageData($response, $style, $transport);

                        Log::info($successLogMessage, [
                            'style' => $style,
                            'transport' => $transport,
                            'phone' => $phone,
                            'message_id' => $this->lastAcceptedMessage['message_id'] ?? null,
                            'status' => $response->status(),
                            'payload_diagnostics' => $this->payloadDiagnostics($payload),
                            'payload_preview' => $this->truncateBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
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
                        'payload_preview' => $this->truncateBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
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

    private function payloadDiagnostics(array $payload): array
    {
        $content = data_get($payload, 'Messages.0.Content')
            ?? data_get($payload, 'messages.0.content')
            ?? $payload;

        return [
            'top_level_keys' => array_keys($payload),
            'content_keys' => is_array($content) ? array_keys($content) : [],
            'parameters_count' => is_array(data_get($content, 'Parameters')) ? count(data_get($content, 'Parameters')) : null,
            'body_params_count' => is_array(data_get($content, 'bodyParams'))
                ? count(data_get($content, 'bodyParams'))
                : null,
            'template_components_body_parameters_count' => is_array(data_get($content, 'Template.Components.0.Parameters'))
                ? count(data_get($content, 'Template.Components.0.Parameters'))
                : null,
            'template_lower_components_body_parameters_count' => is_array(data_get($content, 'template.components.0.parameters'))
                ? count(data_get($content, 'template.components.0.parameters'))
                : null,
            'template_data_body_placeholders_count' => is_array(data_get($content, 'TemplateData.Body.Placeholders'))
                ? count(data_get($content, 'TemplateData.Body.Placeholders'))
                : null,
            'template_data_body_localizable_params_count' => is_array(data_get($content, 'TemplateData.Body.localizable_params'))
                ? count(data_get($content, 'TemplateData.Body.localizable_params'))
                : null,
            'template_data_lower_body_placeholders_count' => is_array(data_get($content, 'templateData.body.placeholders'))
                ? count(data_get($content, 'templateData.body.placeholders'))
                : null,
        ];
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

    private function resolveDocumentEndpoint(string $apiUrl): string
    {
        $path = trim((string) config('services.javna.whatsapp_document_path', '/whatsapp/v1.0/message/document'));

        return rtrim($apiUrl, '/') . '/' . ltrim($path, '/');
    }

    private function saveBase64ToPublicStorage(string $base64Data, string $filename): ?string
    {
        try {
            $decoded = base64_decode($base64Data, true);
            if ($decoded === false) {
                return null;
            }

            $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
            if (! str_ends_with(strtolower($sanitizedFilename), '.pdf')) {
                $sanitizedFilename .= '.pdf';
            }

            $directory = public_path('media/pdfs');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $filePath = $directory . '/' . $sanitizedFilename;
            file_put_contents($filePath, $decoded);

            return url('media/pdfs/' . $sanitizedFilename);
        } catch (\Throwable $e) {
            Log::warning('Failed to save base64 document to public storage.', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildDocumentPayloadCandidates(
        string $phone,
        ?string $fileUrl,
        ?string $base64Data,
        string $filename,
        ?string $caption
    ): array {
        $sender = trim((string) config('services.javna.whatsapp_sender', ''));
        $channelId = trim((string) config('services.javna.whatsapp_channel_id', ''));

        return [
            'javna_content_document' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'Content' => array_filter([
                    'Type' => 'document',
                    'Document' => array_filter([
                        'Url' => $fileUrl,
                        'Filename' => $filename,
                        'Caption' => $caption,
                        'Data' => $base64Data,
                    ]),
                ]),
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_media' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'MediaUrl' => $fileUrl,
                'MediaType' => 'document',
                'FileName' => $filename,
                'Caption' => $caption,
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'meta_document' => array_filter([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'document',
                'document' => array_filter([
                    'link' => $fileUrl,
                    'filename' => $filename,
                    'caption' => $caption,
                ]),
                'from' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'simple_document' => array_filter([
                'to' => $phone,
                'type' => 'document',
                'url' => $fileUrl,
                'filename' => $filename,
                'caption' => $caption,
                'sender' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
            'javna_document_lower' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'content' => array_filter([
                    'type' => 'document',
                    'url' => $fileUrl,
                    'filename' => $filename,
                    'caption' => $caption,
                ]),
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function normalizePhoneForWhatsApp(string $phone): ?string
    {
        $validated = app(TaqnyatSmsService::class)->validatePhoneNumber($phone);
        if (! $validated) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $validated);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '966') && preg_match('/^9665\d{8}$/', $digits) === 1) {
            return $digits;
        }

        if (preg_match('/^05\d{8}$/', $digits) === 1) {
            return '966' . substr($digits, 1);
        }

        if (preg_match('/^5\d{8}$/', $digits) === 1) {
            return '966' . $digits;
        }

        return null;
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

    private function responseRepresentsAcceptedSend($response): bool
    {
        $data = $response->json();
        if (! is_array($data)) {
            return $response->successful();
        }

        $accepted = (int) data_get($data, 'stats.accepted', 0);
        $rejected = (int) data_get($data, 'stats.rejected', 0);

        if ($accepted > 0 && $rejected === 0) {
            return true;
        }

        if (array_key_exists('stats', $data) || array_key_exists('acceptedMessages', $data) || array_key_exists('rejectedMessages', $data)) {
            return false;
        }

        return $response->successful();
    }

    private function extractAcceptedMessageData($response, string $style, string $transport): array
    {
        $data = $response->json();
        $acceptedMessage = is_array($data) ? data_get($data, 'acceptedMessages.0', []) : [];

        if (! is_array($acceptedMessage)) {
            $acceptedMessage = [];
        }

        return [
            'message_id' => data_get($acceptedMessage, 'messageId') ?: data_get($acceptedMessage, 'message_id') ?: data_get($data, 'messageId'),
            'from' => data_get($acceptedMessage, 'from'),
            'to' => data_get($acceptedMessage, 'to'),
            'status' => data_get($acceptedMessage, 'messageStatus.status') ?: data_get($data, 'status'),
            'style' => $style,
            'transport' => $transport,
            'raw' => is_array($data) ? $data : null,
        ];
    }
}
