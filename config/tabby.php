<?php

return [
    'public_key'    => env('TABBY_PUBLIC_KEY'),
    'secret_key'    => env('TABBY_SECRET_KEY'),
    'merchant_code' => env('TABBY_MERCHANT_CODE'),
    'base_url'      => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
];