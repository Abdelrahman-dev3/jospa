<?php

$appHost = (string) (parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?? '');
$defaultBaseUrl = 'https://api.tabby.ai';

if ($appHost !== '' && (
    str_contains($appHost, '.sa')
    || str_contains($appHost, '-sa.')
    || str_contains($appHost, 'sa.')
)) {
    $defaultBaseUrl = 'https://api.tabby.sa';
}

return [
    'public_key'    => env('TABBY_PUBLIC_KEY'),
    'secret_key'    => env('TABBY_SECRET_KEY'),
    'merchant_code' => env('TABBY_MERCHANT_CODE'),
    'base_url'      => env('TABBY_BASE_URL', $defaultBaseUrl),
];
