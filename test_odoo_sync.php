<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\User;
use App\Services\OdooBookingSyncService;
use Illuminate\Support\Facades\Http;

$user = new User([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'mobile' => '966500000000',
    'email' => 'test@example.com',
]);
$user->id = 1;

$invoice = new Invoice([
    'user_id' => 1,
    'payment_method' => 'card',
    'final_total' => 100.0,
    'discount_amount' => 0.0,
    'gift_code' => 'GC-20260728080251-UDED7P',
    'gift_amount' => 50.0,
    'coupon_code' => null,
    'cart_ids' => [1],
]);
$invoice->id = 999999;
$invoice->setRelation('user', $user);
$invoice->created_at = now();

echo "Configured Odoo DB: " . config('services.odoo.db') . "\n";
echo "Configured Odoo URL: " . config('services.odoo.booking_create_url') . "\n";
echo "Test Invoice giftcard_code: {$invoice->gift_code}\n";
echo "Test Invoice giftcard_amount: {$invoice->gift_amount}\n";

$service = app(OdooBookingSyncService::class);
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('buildPayload');
$method->setAccessible(true);

$payload = $method->invoke($service, $invoice, collect(), collect());

echo "\n--- GENERATED ODOO PAYLOAD ---\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

echo "\n--- SENDING TEST HTTP REQUEST TO ODOO ---\n";
$url = config('services.odoo.booking_create_url');
$authHeaders = app(App\Services\OdooGiftCardService::class)->authHeaders();

$fullRequestPayload = [
    'event' => 'odoo_create_booking',
    'data' => $payload,
];

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
