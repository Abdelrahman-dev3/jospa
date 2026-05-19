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
        'entity_id' => env('HYPERPAY_ENTITY_ID'),
        'token'     => env('HYPERPAY_TOKEN'),
        'base_url'  => env('HYPERPAY_BASE_URL'),
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
    ],


];
