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

        $this->assertSame('jospa_invoice_pdf_sa', data_get($payload, 'messages.0.content.templateName'));
        $this->assertSame('ar', data_get($payload, 'messages.0.content.templateLanguage'));
        $this->assertSame('document', data_get($payload, 'messages.0.content.header.headerFormat'));
        $this->assertSame('https://example.com/document.pdf', data_get($payload, 'messages.0.content.header.mediaUrl'));
        $this->assertSame('Document.pdf', data_get($payload, 'messages.0.content.header.documentFileName'));
        $this->assertSame(
            6,
            count(data_get($payload, 'messages.0.content.bodyParams', []))
        );
        $this->assertNull(data_get($payload, 'messages.0.content.template'));
        $this->assertNull(data_get($payload, 'messages.0.content.templateData'));
        $this->assertNull(data_get($payload, 'messages.0.content.TemplateData'));
    }

    public function test_gift_card_document_template_payload_includes_four_body_params(): void
    {
        $payload = $this->firstDocumentTemplatePayload(['sender', 'phone', 'code', 'message']);

        $this->assertSame(
            4,
            count(data_get($payload, 'messages.0.content.bodyParams', []))
        );
    }

    public function test_preferred_document_template_payload_skips_nested_meta_template_shape(): void
    {
        $candidates = $this->documentTemplatePayloadCandidates(['A', 'B', 'C', 'D', 'E', 'F']);

        $this->assertSame('javna_official_document_template_header_body_params', array_key_first($candidates));
        $this->assertArrayNotHasKey('javna_document_template_destinations_content_template_components', $candidates);
        $this->assertArrayNotHasKey('javna_document_template_data_header_media_url', $candidates);
    }

    public function test_old_document_payload_style_is_mapped_to_official_javna_schema(): void
    {
        $candidates = $this->documentTemplatePayloadCandidates(
            ['A', 'B', 'C', 'D', 'E', 'F'],
            'javna_document_template_data_header_media_url'
        );

        $this->assertSame(['javna_official_document_template_header_body_params'], array_keys($candidates));
        $payload = $candidates['javna_official_document_template_header_body_params'];

        $this->assertSame('document', data_get($payload, 'messages.0.content.header.headerFormat'));
        $this->assertSame(
            6,
            count(data_get($payload, 'messages.0.content.bodyParams', []))
        );
        $this->assertNull(data_get($payload, 'messages.0.content.templateData'));
    }

    private function firstDocumentTemplatePayload(array $variables): array
    {
        $candidates = $this->documentTemplatePayloadCandidates($variables);

        return $candidates[array_key_first($candidates)];
    }

    private function documentTemplatePayloadCandidates(
        array $variables,
        string $payloadStyle = 'javna_official_template_body_params'
    ): array
    {
        config([
            'services.javna.whatsapp_payload_style' => $payloadStyle,
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
            'Document'
        );
    }
}
