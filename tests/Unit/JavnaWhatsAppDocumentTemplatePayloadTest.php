<?php

namespace Tests\Unit;

use App\Services\WhatsApp\JavnaWhatsAppService;
use ReflectionMethod;
use Tests\TestCase;

class JavnaWhatsAppDocumentTemplatePayloadTest extends TestCase
{
    public function test_invoice_document_template_payload_includes_six_body_params(): void
    {
        $payload = $this->firstDocumentTemplatePayload(['A', 'B', 'C', 'D', 'E', 'F']);

        $this->assertSame('document', data_get($payload, 'messages.0.content.template.components.0.parameters.0.type'));
        $this->assertSame(
            6,
            count(data_get($payload, 'messages.0.content.template.components.1.parameters', []))
        );
    }

    public function test_gift_card_document_template_payload_includes_four_body_params(): void
    {
        $payload = $this->firstDocumentTemplatePayload(['sender', 'phone', 'code', 'message']);

        $this->assertSame(
            4,
            count(data_get($payload, 'messages.0.content.template.components.1.parameters', []))
        );
    }

    public function test_template_data_fallback_payload_keeps_localizable_body_params(): void
    {
        $payload = $this->documentTemplatePayload(
            'javna_document_template_data_header_media_url',
            ['A', 'B', 'C', 'D', 'E', 'F']
        );

        $this->assertSame('DOCUMENT', data_get($payload, 'messages.0.content.templateData.header.type'));
        $this->assertSame(
            6,
            count(data_get($payload, 'messages.0.content.templateData.body.localizable_params', []))
        );
    }

    private function firstDocumentTemplatePayload(array $variables): array
    {
        $candidates = $this->documentTemplatePayloadCandidates($variables);

        return $candidates[array_key_first($candidates)];
    }

    private function documentTemplatePayload(string $style, array $variables): array
    {
        return $this->documentTemplatePayloadCandidates($variables)[$style];
    }

    private function documentTemplatePayloadCandidates(array $variables): array
    {
        config([
            'services.javna.whatsapp_payload_style' => 'javna_official_template_body_params',
            'services.javna.whatsapp_sender' => '966920012924',
        ]);

        $service = new JavnaWhatsAppService();
        $method = new ReflectionMethod($service, 'buildDocumentTemplatePayloadCandidates');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            '966555123456',
            'jospa_invoice_pdf_sa',
            'ar',
            $variables,
            'https://example.com/document.pdf',
            null,
            'Document.pdf'
        );
    }
}
