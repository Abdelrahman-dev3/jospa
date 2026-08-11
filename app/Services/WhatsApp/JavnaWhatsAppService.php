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

    /**
     * Returns true when the jospa_invoice_pdf_sa document-template is configured.
     * The template name is read from JAVNA_WHATSAPP_INVOICE_PDF_TEMPLATE_NAME (.env).
     * Falls back to the literal value 'jospa_invoice_pdf_sa' when the env key is missing.
     */
    public function shouldUseInvoicePdfTemplate(): bool
    {
        return $this->isEnabled() && filled($this->resolveInvoicePdfTemplateName());
    }

    public function resolveInvoicePdfTemplateName(): string
    {
        return trim((string) config(
            'services.javna.whatsapp_invoice_pdf_template_name',
            'jospa_invoice_pdf_sa'
        ));
    }

    public function resolveGiftCardPdfTemplateName(): string
    {
        return trim((string) config(
            'services.javna.whatsapp_gift_card_pdf_template_name',
            'jospa_giftcard_pdf_sa'
        ));
    }

    public function resolveGiftCardRecipientTemplateName(): string
    {
        return trim((string) config('services.javna.whatsapp_gift_card_recipient_template_name', ''));
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

        if ($this->templateRequiresDocumentHeader($resolvedTemplateName)) {
            Log::error('WhatsApp template skipped because it requires a DOCUMENT header but no document was provided.', [
                'phone' => $normalizedPhone,
                'template_name' => $resolvedTemplateName,
            ]);

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
        $filename = $this->normalizePdfFilename($filename);

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

    /**
     * Send a WhatsApp template whose header is a PDF document.
     *
     * The template (e.g. jospa_invoice_pdf_sa) must be a document-header template
     * approved by Meta / the WhatsApp BSP. It has:
     *   - Header component: type = document  (the invoice PDF)
     *   - Body component:   6 text variables  (customer name, invoice #, order type,
     *                                          order details, branch, amount)
     *
     * @param string      $phone            Destination phone (any local format – will be normalised).
     * @param string      $fileUrlOrBase64  Public URL *or* raw base64 string of the PDF.
     * @param string      $filename         PDF filename shown to the recipient.
     * @param array       $variables        Body template variables (ordered list of strings).
     * @param string|null $templateName     Override the template name (default: resolveInvoicePdfTemplateName()).
     * @param string|null $language         Template language code (default: JAVNA_WHATSAPP_TEMPLATE_LANGUAGE or 'ar').
     * @param bool        $fallbackToPlainDocument Send a non-template document if the template fails.
     */
    public function sendTemplateWithDocument(
        string $phone,
        string $fileUrlOrBase64,
        string $filename = 'Invoice.pdf',
        array $variables = [],
        ?string $templateName = null,
        ?string $language = null,
        bool $fallbackToPlainDocument = true
    ): bool {
        $this->lastAcceptedMessage = null;

        if (! $this->isEnabled()) {
            Log::warning('WhatsApp document-template sending is disabled or incomplete.', [
                'enabled'        => (bool) config('services.javna.whatsapp_enabled'),
                'missing_config' => $this->missingConfigKeys(),
            ]);

            return false;
        }

        $normalizedPhone = $this->normalizePhoneForWhatsApp($phone);
        if ($normalizedPhone === null) {
            Log::warning('WhatsApp document-template skipped because the phone number is invalid.', [
                'original_phone' => $phone,
            ]);

            return false;
        }

        $resolvedTemplateName = trim((string) ($templateName ?: $this->resolveInvoicePdfTemplateName()));
        if ($resolvedTemplateName === '') {
            Log::warning('WhatsApp document-template skipped because the template name is missing.');

            return false;
        }

        $resolvedLanguage = trim((string) ($language ?: config('services.javna.whatsapp_template_language', 'ar')));
        $apiUrl           = (string) config('services.javna.whatsapp_api_url');
        $apiToken         = (string) config('services.javna.whatsapp_api_token');
        $timeout          = (int)    config('services.javna.whatsapp_timeout', 15);
        $filename         = $this->normalizePdfFilename($filename);

        // Resolve document: prefer a public URL so the provider can fetch it directly.
        $fileUrl    = null;
        $base64Data = null;

        if (str_starts_with($fileUrlOrBase64, 'http://') || str_starts_with($fileUrlOrBase64, 'https://')) {
            $fileUrl = $fileUrlOrBase64;
        } else {
            $base64Data = preg_replace('#^data:application/\w+;base64,#i', '', trim($fileUrlOrBase64));
            $base64Data = str_replace([' ', "\r", "\n"], '', (string) $base64Data);

            // Try to persist the file so the provider can download it via URL.
            $savedUrl = $this->saveBase64ToPublicStorage($base64Data, $filename);
            if ($savedUrl) {
                $fileUrl = $savedUrl;
            }
        }

        $payloadCandidates = $this->buildDocumentTemplatePayloadCandidates(
            phone: $normalizedPhone,
            templateName: $resolvedTemplateName,
            language: $resolvedLanguage,
            variables: $variables,
            fileUrl: $fileUrl,
            base64Data: $base64Data,
            filename: $filename,
        );

        Log::info('Attempting outbound WhatsApp document-template send.', [
            'phone'            => $normalizedPhone,
            'api_url'          => $apiUrl,
            'timeout'          => $timeout,
            'template_name'    => $resolvedTemplateName,
            'template_language' => $resolvedLanguage,
            'filename'         => $filename,
            'file_url'         => $fileUrl,
            'has_base64'       => ! empty($base64Data),
            'payload_styles'   => array_keys($payloadCandidates),
            'variables_count'  => count($variables),
        ]);

        $sent = $this->deliverPayloadCandidates(
            endpoint: $this->resolveTemplateEndpoint($apiUrl),
            apiToken: $apiToken,
            timeout: $timeout,
            phone: $normalizedPhone,
            payloadCandidates: $payloadCandidates,
            successLogMessage: 'Outbound WhatsApp document-template sent successfully.',
            rejectedLogMessage: 'WhatsApp provider rejected outbound document-template payload.',
            exceptionLogMessage: 'Failed to send outbound WhatsApp document-template.',
            finalErrorLogMessage: 'All outbound WhatsApp document-template payload styles failed.'
        );

        // Fallback: if the template send failed but we have a public URL, try sending
        // the PDF as a plain document message so the customer still receives the file.
        if ($fallbackToPlainDocument && ! $sent && $fileUrl !== null) {
            Log::info('Document-template send failed – falling back to plain sendDocument.', [
                'phone'    => $normalizedPhone,
                'filename' => $filename,
                'url'      => $fileUrl,
            ]);

            return $this->sendDocument(
                phone: $phone,
                fileUrlOrBase64: $fileUrl,
                filename: $filename,
            );
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
            ], fn($value) => $value !== null && $value !== ''),
            'javna_text' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'Text' => $message,
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_text_lower' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'text' => $message,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'meta' => array_filter([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
                'from' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'simple_body' => array_filter([
                'to' => $phone,
                'body' => $message,
                'sender' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'simple_message' => array_filter([
                'to' => $phone,
                'message' => $message,
                'sender' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
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
        $textParameters = array_map(fn($value) => [
            'type' => 'text',
            'text' => $value,
        ], $parameterValues);
        $localizableParameters = array_map(fn($value) => [
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                        ], fn($value) => $value !== null && $value !== ''),
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                        ], fn($value) => $value !== null && $value !== ''),
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_localizable_params' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'localizable_params' => $localizableParameters,
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language_parameters_objects' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'Parameters' => $textParameters,
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template_messages_destinations_string_template_language' => array_filter([
                'Messages' => [[
                    'From' => $sender,
                    'Destinations' => [$phone],
                    'Content' => array_filter([
                        'Type' => 'template',
                        'TemplateName' => $templateName,
                        'TemplateLanguage' => $language,
                        'Parameters' => $parameterValues,
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ]],
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template_messages' => array_filter([
                'Messages' => [[
                    'To' => $phone,
                    'From' => $sender,
                    'TemplateName' => $templateName,
                    'Language' => $language,
                    'Parameters' => $parameterValues,
                    'Namespace' => $namespace !== '' ? $namespace : null,
                ]],
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template_messages_lower' => array_filter([
                'messages' => [[
                    'to' => $phone,
                    'from' => $sender,
                    'templateName' => $templateName,
                    'language' => $language,
                    'parameters' => $parameterValues,
                    'namespace' => $namespace !== '' ? $namespace : null,
                ]],
            ], fn($value) => $value !== null && $value !== ''),
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
                    ], fn($value) => $value !== null && $value !== ''),
                ], fn($value) => $value !== null && $value !== ''),
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'TemplateName' => $templateName,
                'Language' => $language,
                'Parameters' => $parameterValues,
                'ChannelId' => $channelId !== '' ? $channelId : null,
                'Namespace' => $namespace !== '' ? $namespace : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_template_lower' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'templateName' => $templateName,
                'language' => $language,
                'parameters' => $parameterValues,
                'channelId' => $channelId !== '' ? $channelId : null,
                'namespace' => $namespace !== '' ? $namespace : null,
            ], fn($value) => $value !== null && $value !== ''),
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
                ], fn($value) => $value !== null && $value !== ''),
            ], fn($value) => $value !== null && $value !== ''),
        ];

        if ($preferredStyle !== '' && $preferredStyle !== 'auto' && isset($candidates[$preferredStyle])) {
            return [$preferredStyle => $candidates[$preferredStyle]];
        }

        return $candidates;
    }

    /**
     * Build payload candidates for a document-header template (e.g. jospa_invoice_pdf_sa).
     *
     * WhatsApp document-header templates have two components:
     *   1. header  – type: document  (the PDF attachment)
     *   2. body    – text parameters (the 6 invoice variables)
     *
     * We try several payload shapes because different Javna API versions / gateway
     * wrappers accept slightly different JSON structures.
     */
    private function buildDocumentTemplatePayloadCandidates(
        string  $phone,
        string  $templateName,
        string  $language,
        array   $variables,
        ?string $fileUrl,
        ?string $base64Data,
        string  $filename
    ): array {
        $sender           = trim((string) config('services.javna.whatsapp_sender', ''));
        $channelId        = trim((string) config('services.javna.whatsapp_channel_id', ''));
        $preferredStyle   = trim((string) config('services.javna.whatsapp_payload_style', 'auto'));
        $filename         = $this->normalizePdfFilename($filename);
        $parameterValues  = $this->normalizeTemplateVariables($variables);
        $textParameters   = array_map(fn($v) => ['type' => 'text', 'text' => $v], $parameterValues);
        $localizableParameters = array_map(fn($value) => [
            'default' => $value,
        ], $parameterValues);

        $headerDocument = array_filter([
            'type'     => 'document',
            'document' => array_filter([
                'link'     => $fileUrl,
                'filename' => $filename,
                'mime_type' => 'application/pdf',
                'id'       => null,         // placeholder – some providers use media_id instead
            ], fn($v) => $v !== null && $v !== ''),
        ]);

        $headerDocumentBase64 = array_filter([
            'type'     => 'document',
            'document' => array_filter([
                'data'     => $base64Data,
                'filename' => $filename,
                'link'     => $fileUrl,
                'mime_type' => 'application/pdf',
            ], fn($v) => $v !== null && $v !== ''),
        ]);

        $candidates = [
            // ── Meta-style payload (most common) ──────────────────────────────────
            'javna_document_template_destinations_content_template_components' => array_filter([
                'messages' => [[
                    'from' => $sender,
                    'destinations' => [$phone],
                    'to' => $phone,
                    'content' => array_filter([
                        'type' => 'template',
                        'template' => [
                            'name' => $templateName,
                            'language' => [
                                'code' => $language,
                            ],
                            'components' => array_filter([
                                $fileUrl ? [
                                    'type' => 'header',
                                    'parameters' => [[
                                        'type' => 'document',
                                        'document' => array_filter([
                                            'link' => $fileUrl,
                                            'filename' => $filename,
                                            'mime_type' => 'application/pdf',
                                        ], fn($v) => $v !== null && $v !== ''),
                                    ]],
                                ] : null,
                                [
                                    'type' => 'body',
                                    'parameters' => $textParameters,
                                ],
                            ]),
                        ],
                    ], fn($v) => $v !== null && $v !== ''),
                ]],
            ], fn($v) => $v !== null && $v !== ''),

            'meta_document_template' => array_filter([
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $phone,
                'from'              => $sender !== '' ? $sender : null,
                'channelId'         => $channelId !== '' ? $channelId : null,
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $language],
                    'components' => array_filter([
                        $fileUrl ? [
                            'type'       => 'header',
                            'parameters' => [[
                                'type'     => 'document',
                                'document' => array_filter([
                                    'link'     => $fileUrl,
                                    'filename' => $filename,
                                    'mime_type' => 'application/pdf',
                                ]),
                            ]],
                        ] : null,
                        [
                            'type'       => 'body',
                            'parameters' => $textParameters,
                        ],
                    ]),
                ],
            ], fn($v) => $v !== null && $v !== ''),

            // ── Meta-style with base64 document in header ─────────────────────────
            'meta_document_template_base64' => array_filter([
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $phone,
                'from'              => $sender !== '' ? $sender : null,
                'channelId'         => $channelId !== '' ? $channelId : null,
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $language],
                    'components' => array_filter([
                        $base64Data ? [
                            'type'       => 'header',
                            'parameters' => [[
                                'type'     => 'document',
                                'document' => array_filter([
                                    'data'     => $base64Data,
                                    'filename' => $filename,
                                    'link'     => $fileUrl,
                                    'mime_type' => 'application/pdf',
                                ], fn($v) => $v !== null && $v !== ''),
                            ]],
                        ] : null,
                        [
                            'type'       => 'body',
                            'parameters' => $textParameters,
                        ],
                    ]),
                ],
            ], fn($v) => $v !== null && $v !== ''),

            // ── Javna-style: Messages / Content / Template / Components ───────────
            'javna_document_template_components' => array_filter([
                'Messages' => [[
                    'From'         => $sender,
                    'Destinations' => [$phone],
                    'Content'      => array_filter([
                        'Type'     => 'template',
                        'Template' => [
                            'Name'       => $templateName,
                            'Language'   => ['Code' => $language],
                            'Components' => array_filter([
                                $fileUrl ? [
                                    'Type'       => 'header',
                                    'Parameters' => [[
                                        'Type'     => 'document',
                                        'Document' => array_filter([
                                            'Url'      => $fileUrl,
                                            'Filename' => $filename,
                                        ]),
                                    ]],
                                ] : null,
                                [
                                    'Type'       => 'body',
                                    'Parameters' => $textParameters,
                                ],
                            ]),
                        ],
                    ], fn($v) => $v !== null && $v !== ''),
                ]],
            ], fn($v) => $v !== null && $v !== ''),

            // ── Javna official body-params style with document header ──────────────
            'javna_document_template_data_header_media_url' => array_filter([
                'messages' => [[
                    'from'         => $sender,
                    'destinations' => [$phone],
                    'content'      => array_filter([
                        'type'             => 'template',
                        'templateName'     => $templateName,
                        'templateLanguage' => $language,
                        'language'         => $language,
                        'template'         => [
                            'name'       => $templateName,
                            'language'   => ['code' => $language],
                            'components' => array_filter([
                                $fileUrl ? [
                                    'type'       => 'header',
                                    'parameters' => [[
                                        'type'     => 'document',
                                        'document' => array_filter([
                                            'link'     => $fileUrl,
                                            'filename' => $filename,
                                            'mime_type' => 'application/pdf',
                                        ], fn($v) => $v !== null && $v !== ''),
                                    ]],
                                ] : null,
                                [
                                    'type'       => 'body',
                                    'parameters' => $textParameters,
                                ],
                            ]),
                        ],
                        'templateData'     => array_filter([
                            'header' => $fileUrl ? array_filter([
                                'type'     => 'DOCUMENT',
                                'mediaUrl' => $fileUrl,
                                'filename' => $filename,
                                'mimeType' => 'application/pdf',
                            ], fn($v) => $v !== null && $v !== '') : null,
                            'Body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                            'body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                        ], fn($v) => ! empty($v)),
                        'TemplateData'     => array_filter([
                            'Header' => $fileUrl ? array_filter([
                                'Type'     => 'DOCUMENT',
                                'MediaUrl' => $fileUrl,
                                'Filename' => $filename,
                                'MimeType' => 'application/pdf',
                            ], fn($v) => $v !== null && $v !== '') : null,
                            'Body' => [
                                'localizable_params' => $localizableParameters,
                            ],
                        ], fn($v) => ! empty($v)),
                    ], fn($v) => $v !== null && $v !== ''),
                ]],
            ], fn($v) => $v !== null && $v !== ''),

            'javna_document_template_data_header_media_url_upper' => array_filter([
                'Messages' => [[
                    'From'         => $sender,
                    'Destinations' => [$phone],
                    'Content'      => array_filter([
                        'Type'             => 'template',
                        'TemplateName'     => $templateName,
                        'TemplateLanguage' => $language,
                        'TemplateData'     => array_filter([
                            'Header' => $fileUrl ? array_filter([
                                'Type'     => 'DOCUMENT',
                                'MediaUrl' => $fileUrl,
                                'Filename' => $filename,
                                'MimeType' => 'application/pdf',
                            ], fn($v) => $v !== null && $v !== '') : null,
                            'Body' => [
                                'localizable_params' => $localizableParameters,
                                'Placeholders' => $parameterValues,
                            ],
                        ], fn($v) => ! empty($v)),
                    ], fn($v) => $v !== null && $v !== ''),
                ]],
            ], fn($v) => $v !== null && $v !== ''),

            'javna_official_document_template_body_params_typed_header' => array_filter([
                'messages' => [[
                    'from'         => $sender,
                    'destinations' => [$phone],
                    'content'      => array_filter([
                        'templateName'     => $templateName,
                        'templateLanguage' => $language,
                        'headerParams'     => $fileUrl ? [[
                            'type'     => 'document',
                            'document' => array_filter([
                                'link'     => $fileUrl,
                                'url'      => $fileUrl,
                                'filename' => $filename,
                            ], fn($v) => $v !== null && $v !== ''),
                        ]] : null,
                        'bodyParams' => $parameterValues,
                    ], fn($v) => $v !== null && $v !== ''),
                ]],
            ], fn($v) => $v !== null && $v !== ''),

            'javna_official_document_template_body_params' => array_filter([
                'messages' => [[
                    'from'         => $sender,
                    'destinations' => [$phone],
                    'content'      => array_filter([
                        'templateName'     => $templateName,
                        'templateLanguage' => $language,
                        'headerParams'     => array_filter([
                            'document' => array_filter([
                                'url'      => $fileUrl,
                                'filename' => $filename,
                            ], fn($v) => $v !== null && $v !== ''),
                        ], fn($v) => ! empty($v)),
                        'bodyParams' => $parameterValues,
                    ], fn($v) => $v !== null && $v !== ''),
                ]],
            ], fn($v) => $v !== null && $v !== ''),
        ];

        if ($preferredStyle === 'javna_official_template_body_params') {
            $orderedCandidates = [];

            foreach ([
                'javna_document_template_data_header_media_url',
                'javna_document_template_data_header_media_url_upper',
                'javna_official_document_template_body_params_typed_header',
                'javna_official_document_template_body_params',
            ] as $style) {
                if (isset($candidates[$style])) {
                    $orderedCandidates[$style] = $candidates[$style];
                }
            }

            return $orderedCandidates;
        }

        $preferredDocumentStyle = $preferredStyle;

        if ($preferredDocumentStyle !== '' && $preferredDocumentStyle !== 'auto' && isset($candidates[$preferredDocumentStyle])) {
            return [$preferredDocumentStyle => $candidates[$preferredDocumentStyle]];
        }

        return $candidates;
    }

    private function templateRequiresDocumentHeader(string $templateName): bool
    {
        return in_array($templateName, array_filter([
            $this->resolveInvoicePdfTemplateName(),
            $this->resolveGiftCardPdfTemplateName(),
        ]), true);
    }

    private function normalizeTemplateVariables(array $variables): array
    {
        return array_values(array_map(function ($value) {
            $normalizedValue = trim((string) $value);

            return $normalizedValue !== '' ? $normalizedValue : '-';
        }, $variables));
    }

    private function normalizePdfFilename(string $filename): string
    {
        $normalizedFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', trim($filename));
        $normalizedFilename = is_string($normalizedFilename) && $normalizedFilename !== ''
            ? $normalizedFilename
            : 'document.pdf';

        return str_ends_with(strtolower($normalizedFilename), '.pdf')
            ? $normalizedFilename
            : $normalizedFilename . '.pdf';
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
            ?? data_get($payload, 'Content')
            ?? data_get($payload, 'content')
            ?? $payload;

        return [
            'top_level_keys' => array_keys($payload),
            'content_keys' => is_array($content) ? array_keys($content) : [],
            'parameters_count' => is_array(data_get($content, 'Parameters')) ? count(data_get($content, 'Parameters')) : null,
            'body_params_count' => is_array(data_get($content, 'bodyParams'))
                ? count(data_get($content, 'bodyParams'))
                : null,
            'header_params_count' => is_array(data_get($content, 'headerParams'))
                ? count(data_get($content, 'headerParams'))
                : null,
            'template_data_header_type' => data_get($content, 'TemplateData.Header.Type')
                ?? data_get($content, 'templateData.header.type'),
            'template_data_header_media_url_present' => filled(data_get($content, 'TemplateData.Header.MediaUrl'))
                || filled(data_get($content, 'templateData.header.mediaUrl')),
            'template_data_header_mime_type' => data_get($content, 'TemplateData.Header.MimeType')
                ?? data_get($content, 'templateData.header.mimeType'),
            'template_header_document_mime_type' => data_get($content, 'Template.Components.0.Parameters.0.Document.MimeType')
                ?? data_get($content, 'template.components.0.parameters.0.document.mime_type'),
            'template_components_body_parameters_count' => $this->countTemplateComponentParameters(
                data_get($content, 'Template.Components')
            ),
            'template_lower_components_body_parameters_count' => $this->countTemplateComponentParameters(
                data_get($content, 'template.components')
            ),
            'template_data_body_placeholders_count' => is_array(data_get($content, 'TemplateData.Body.Placeholders'))
                ? count(data_get($content, 'TemplateData.Body.Placeholders'))
                : null,
            'template_data_body_localizable_params_count' => is_array(data_get($content, 'TemplateData.Body.localizable_params'))
                ? count(data_get($content, 'TemplateData.Body.localizable_params'))
                : null,
            'template_data_lower_body_localizable_params_count' => is_array(data_get($content, 'templateData.body.localizable_params'))
                ? count(data_get($content, 'templateData.body.localizable_params'))
                : null,
            'template_data_lower_body_placeholders_count' => is_array(data_get($content, 'templateData.body.placeholders'))
                ? count(data_get($content, 'templateData.body.placeholders'))
                : null,
        ];
    }

    private function countTemplateComponentParameters(mixed $components): ?int
    {
        if (! is_array($components)) {
            return null;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtolower((string) ($component['type'] ?? $component['Type'] ?? ''));
            if ($type !== 'body') {
                continue;
            }

            $parameters = $component['parameters'] ?? $component['Parameters'] ?? null;

            return is_array($parameters) ? count($parameters) : 0;
        }

        return null;
    }

    private function resolveTransportModes(string $style): array
    {
        if (str_contains($style, 'document') || str_contains($style, 'media')) {
            return ['json'];
        }

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

            $sanitizedFilename = $this->normalizePdfFilename($filename);

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
        $filename = $this->normalizePdfFilename($filename);

        return [
            'javna_document_content_media_url' => array_filter([
                'from' => $sender !== '' ? $sender : null,
                'to' => $phone,
                'content' => array_filter([
                    'MediaUrl' => $fileUrl,
                    'FileName' => $filename,
                    'ContentType' => 'application/pdf',
                    'Caption' => $caption,
                ], fn($value) => $value !== null && $value !== ''),
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_document_content_media_url_upper' => array_filter([
                'From' => $sender !== '' ? $sender : null,
                'To' => $phone,
                'Content' => array_filter([
                    'MediaUrl' => $fileUrl,
                    'FileName' => $filename,
                    'ContentType' => 'application/pdf',
                    'Caption' => $caption,
                ], fn($value) => $value !== null && $value !== ''),
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_content_document' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'Content' => array_filter([
                    'Type' => 'document',
                    'Document' => array_filter([
                        'Url' => $fileUrl,
                        'Filename' => $filename,
                        'MimeType' => 'application/pdf',
                        'Caption' => $caption,
                        'Data' => $base64Data,
                    ]),
                ]),
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_media' => array_filter([
                'To' => $phone,
                'From' => $sender !== '' ? $sender : null,
                'MediaUrl' => $fileUrl,
                'MediaType' => 'document',
                'FileName' => $filename,
                'ContentType' => 'application/pdf',
                'Caption' => $caption,
                'ChannelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'meta_document' => array_filter([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'document',
                'document' => array_filter([
                    'link' => $fileUrl,
                    'filename' => $filename,
                    'mime_type' => 'application/pdf',
                    'caption' => $caption,
                ]),
                'from' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'simple_document' => array_filter([
                'to' => $phone,
                'type' => 'document',
                'url' => $fileUrl,
                'filename' => $filename,
                'mime_type' => 'application/pdf',
                'caption' => $caption,
                'sender' => $sender !== '' ? $sender : null,
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
            'javna_document_lower' => array_filter([
                'to' => $phone,
                'from' => $sender !== '' ? $sender : null,
                'content' => array_filter([
                    'type' => 'document',
                    'url' => $fileUrl,
                    'filename' => $filename,
                    'mime_type' => 'application/pdf',
                    'caption' => $caption,
                ]),
                'channelId' => $channelId !== '' ? $channelId : null,
            ], fn($value) => $value !== null && $value !== ''),
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
        $acceptedMessages = data_get($data, 'acceptedMessages', []);
        $hasTerminalFailure = is_array($acceptedMessages)
            && collect($acceptedMessages)->contains(function ($message) {
                $status = strtolower((string) data_get($message, 'messageStatus.status', data_get($message, 'status')));

                return in_array($status, ['failed', 'halted', 'rejected'], true);
            });

        if ($hasTerminalFailure) {
            return false;
        }

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
