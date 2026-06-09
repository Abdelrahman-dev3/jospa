<?php

return [
    'base_url' => env('URPAY_BASE_URL'),
    'create_order_path' => env('URPAY_CREATE_ORDER_PATH'),
    'verify_order_path' => env('URPAY_VERIFY_ORDER_PATH'),
    'token' => env('URPAY_TOKEN'),
    'merchant_id' => env('URPAY_MERCHANT_ID'),
    'terminal_id' => env('URPAY_TERMINAL_ID'),
    'username' => env('URPAY_USERNAME'),
    'password' => env('URPAY_PASSWORD'),
    'checkout_url_template' => env('URPAY_CHECKOUT_URL_TEMPLATE'),
    'currency' => env('URPAY_CURRENCY', 'SAR'),
];
