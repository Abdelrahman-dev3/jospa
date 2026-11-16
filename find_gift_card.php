<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\GiftCard;
use Modules\Booking\Models\Booking;

echo "--- Searching for GiftCard ref 7555-2559-6e7w ---\n";
$giftCard = GiftCard::where('ref', '7555-2559-6e7w')->orWhere('code', '7555-2559-6e7w')->first();
if ($giftCard) {
    echo "Found GiftCard ID: " . $giftCard->id . "\n";
    echo "Ref: " . $giftCard->ref . "\n";
    echo "Amount: " . $giftCard->amount . ", Subtotal: " . $giftCard->subtotal . ", Balance: " . $giftCard->balance . "\n";
    echo "User ID: " . $giftCard->user_id . "\n";
    echo "Branch ID: " . $giftCard->branch_id . "\n";
    echo "Created at: " . $giftCard->created_at . "\n";
    echo "Raw: " . json_encode($giftCard->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "GiftCard not found by ref.\n";
}

echo "\n--- Searching for Invoice 15781 ---\n";
$inv = Invoice::find(15781);
if ($inv) {
    echo "Found Invoice 15781:\n";
    echo "User ID: " . $inv->user_id . "\n";
    echo "cart_ids: " . json_encode($inv->cart_ids) . "\n";
    echo "gift_ids: " . json_encode($inv->gift_ids) . "\n";
    echo "final_total: " . $inv->final_total . "\n";
    echo "payment_method: " . $inv->payment_method . "\n";
    echo "created_at: " . $inv->created_at . "\n";
} else {
    echo "Invoice 15781 not found.\n";
}

echo "\n--- Searching for Booking 15781 ---\n";
$booking = Booking::find(15781);
if ($booking) {
    echo "Found Booking 15781:\n";
    echo "User ID: " . $booking->user_id . "\n";
    echo "Created at: " . $booking->created_at . "\n";
} else {
    echo "Booking 15781 not found.\n";
}

