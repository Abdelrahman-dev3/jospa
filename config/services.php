<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],
    'hyperpay' => [
        'raw_key'            => env('HYPERPAY_KEY'),
        'entity_id'          => env('HYPERPAY_ENTITY_ID'),
        'entity_id_mada'     => env('HYPERPAY_ENTITY_ID_MADA'),
        'entity_id_applepay' => env('HYPERPAY_ENTITY_ID_APPLEPAY'),
        'token'              => env('HYPERPAY_TOKEN'),
        'base_url'           => env('HYPERPAY_BASE_URL', 'https://eu-prod.oppwa.com'),
        'shopper_result_url' => env('HYPERPAY_SHOPPER_RESULT_URL'),
    ],
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],
    'tap' => [
        'secret_key' => env('TAP_SECRET_KEY'),
    ],
    'odoo' => [
        'booking_create_url' => env('ODOO_BOOKING_CREATE_URL'),
        'db' => env('ODOO_DB'),
        'login' => env('ODOO_LOGIN'),
        'password' => env('ODOO_PASSWORD'),
        'timeout' => env('ODOO_TIMEOUT', 15),
    ],

    'javna' => [
        'enabled' => env('JAVNA_WEBHOOK_ENABLED', true),
        'webhook_secret' => env('JAVNA_WEBHOOK_SECRET'),
        'log_payloads' => env('JAVNA_WEBHOOK_LOG_PAYLOADS', true),
        'whatsapp_enabled' => env('JAVNA_WHATSAPP_ENABLED', false),
        'whatsapp_api_url' => env('JAVNA_WHATSAPP_API_URL'),
        'whatsapp_api_token' => env('JAVNA_WHATSAPP_API_TOKEN'),
        'whatsapp_sender' => env('JAVNA_WHATSAPP_SENDER'),
        'whatsapp_channel_id' => env('JAVNA_WHATSAPP_CHANNEL_ID'),
        'whatsapp_timeout' => (int) env('JAVNA_WHATSAPP_TIMEOUT', 15),
        'whatsapp_payload_style' => env('JAVNA_WHATSAPP_PAYLOAD_STYLE', 'javna_official_template_body_params'),
        'whatsapp_template_name' => env('JAVNA_WHATSAPP_TEMPLATE_NAME'),
        'whatsapp_template_language' => env('JAVNA_WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),
        'whatsapp_template_namespace' => env('JAVNA_WHATSAPP_TEMPLATE_NAMESPACE'),
        'whatsapp_template_path' => env('JAVNA_WHATSAPP_TEMPLATE_PATH', '/whatsapp/v1.0/message/template'),
        // Template that carries the invoice PDF as a document header (jospa_invoice_pdf_sa).
        // Override via JAVNA_WHATSAPP_INVOICE_PDF_TEMPLATE_NAME in .env.
        'whatsapp_invoice_pdf_template_name' => env('JAVNA_WHATSAPP_INVOICE_PDF_TEMPLATE_NAME', 'jospa_invoice_pdf_sa'),
        // Template that carries the gift card PDF as a document header.
        'whatsapp_gift_card_pdf_template_name' => env('JAVNA_WHATSAPP_GIFT_CARD_PDF_TEMPLATE_NAME', 'jospa_giftcard_pdf_sa'),
        // Optional body-only gift-card notification template. Do not point this
        // at the PDF template; PDF templates must be sent with a document header.
        'whatsapp_gift_card_recipient_template_name' => env('JAVNA_WHATSAPP_GIFT_CARD_RECIPIENT_TEMPLATE_NAME'),
    ],


];
