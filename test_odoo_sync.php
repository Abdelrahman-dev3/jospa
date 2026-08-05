<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Services\OdooBookingSyncService;

$invoice = Invoice::latest()->first();

if (!$invoice) {
    echo "NO_INVOICE\n";
    exit(1);
}

echo "Testing Invoice ID: {$invoice->id}\n";
$invoice->gift_code = 'GC-20260728080251-UDED7P';
$invoice->gift_amount = 50.0;
$invoice->save();

echo "Saved gift_code=GC-20260728080251-UDED7P, gift_amount=50.0 on Invoice #{$invoice->id}\n";
echo "Configured Odoo DB: " . config('services.odoo.db') . "\n";
echo "Configured Odoo URL: " . config('services.odoo.booking_create_url') . "\n";

try {
    $synced = app(OdooBookingSyncService::class)->syncPaidInvoice($invoice->id);
    echo "SYNC_SUCCESS: " . var_export($synced, true) . "\n";
} catch (\Throwable $e) {
    echo "SYNC_EXCEPTION: " . $e->getMessage() . "\n";
}
