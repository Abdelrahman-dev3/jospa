<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$payload = [
    'id' => rand(100000, 999999),
    'payment_type_code' => 'CARD',
    'user_id' => 1,
    'client_name' => 'Test Customer',
    'client_mobile' => '966500000000',
    'client_email' => 'test@example.com',
    'coupon_code' => null,
    'coupon_discount' => 0.0,
    'gift_coupon_discount' => 0.0,
    'sub_total' => 100.0,
    'gift_sub_total' => 0.0,
    'tax' => 15.0,
    'gift_tax' => 0.0,
    'total' => 115.0,
    'gift_total' => 0.0,
    'has_gift_cards' => false,
    'tapId' => 'INV-TEST-' . time(),
    'booking_number' => '#JO-' . time(),
    'payment_type' => 'Card',
    'payment_status' => 'Paid',
    'date_of_paid' => date('Y-m-d'),
    'booking_date' => date('d/m/Y'),
    'booking_time' => '14:00',
    'order_services' => [
        [
            'id' => 12,
            'quantity' => 1,
            'price' => 100.0,
        ]
    ],
    'booking_details' => [
        [
            'id' => 1,
            'user_id' => 1,
            'bookingInfo_id' => 999999,
            'booking_date' => date('Y-m-d'),
            'booking_time' => '14:00:00',
            'client_name' => 'Test Customer',
            'client_email' => 'test@example.com',
            'client_phone' => '966500000000',
            'notes' => null,
            'service' => [
                'id' => 12,
                'odoo_id' => 12,
                'name' => 'Test Service',
                'image1' => null,
            ],
            'pricing' => [
                'id' => 12,
                'name' => 'Test Service',
                'price' => 100.0,
                'image' => null,
            ],
            'employee' => [
                'id' => 1,
                'name' => 'Test Employee',
            ],
            'location' => [
                'id' => 1,
                'name' => 'Test Branch',
            ],
        ]
    ],
    'gift_card_details' => [],
    'gift_card' => [
        'code' => 'GC-20260728080251-UDED7P',
        'amount' => 50.0,
    ],
    'giftcard_code' => 'GC-20260728080251-UDED7P',
    'giftcard_amount' => 50.0,
];

$url = config('services.odoo.booking_create_url');
$authHeaders = app(App\Services\OdooGiftCardService::class)->authHeaders();

$fullRequestPayload = [
    'event' => 'odoo_create_booking',
    'data' => $payload,
];

echo "Configured Odoo DB: " . config('services.odoo.db') . "\n";
echo "Sending request to Odoo URL: {$url}\n";
echo "Payload giftcard_code: {$payload['giftcard_code']}\n";
echo "Payload giftcard_amount: {$payload['giftcard_amount']}\n\n";

try {
    $response = Http::timeout(15)
        ->acceptJson()
        ->withHeaders(array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $authHeaders))
        ->post($url, $fullRequestPayload);

    echo "HTTP Status: " . $response->status() . "\n";
    echo "HTTP Response Body: " . $response->body() . "\n";
} catch (\Throwable $e) {
    echo "HTTP Exception: " . $e->getMessage() . "\n";
}
