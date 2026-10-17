<?php
/**
 * Script to test BookingAvailabilityService on the server.
 * Run with: php artisan tinker --execute="..."
 * Or SSH: php test_booking_window.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BookingAvailabilityService;
use App\Models\Setting;
use Carbon\Carbon;

function testDate($date) {
    return BookingAvailabilityService::isDateAllowed($date) ? 'ALLOWED' : 'BLOCKED';
}

function saveSetting($name, $val) {
    Setting::where('name', $name)->delete(); // force hard-delete just in case
    Setting::create(['name' => $name, 'val' => $val, 'type' => 'string']);
}

$today = Carbon::today();
echo "\n" . str_repeat('=', 60) . "\n";
echo "  BOOKING WINDOW TEST - " . $today->toDateString() . "\n";
echo str_repeat('=', 60) . "\n\n";

// ─── TEST 1: Restriction DISABLED ───────────────────────────
echo "--- TEST 1: Restriction DISABLED ---\n";
saveSetting('booking_window_restriction', '0');
echo "Restriction ON: " . (BookingAvailabilityService::isRestrictionEnabled() ? 'YES' : 'NO') . "\n";
echo "Yesterday: " . testDate(Carbon::yesterday()) . " (expected: BLOCKED)\n";
echo "Tomorrow: " . testDate(Carbon::tomorrow()) . " (expected: ALLOWED)\n";
echo "In 6 months: " . testDate($today->copy()->addMonths(6)) . " (expected: ALLOWED)\n";
echo "In 13 months: " . testDate($today->copy()->addMonths(13)) . " (expected: BLOCKED - >1yr)\n\n";

// ─── TEST 2: Rolling 1 Month ─────────────────────────────────
echo "--- TEST 2: ROLLING Restriction (1 month) ---\n";
saveSetting('booking_window_restriction', '1');
saveSetting('booking_window_type', 'rolling');
saveSetting('booking_advance_months', '1');
saveSetting('booking_advance_days', '0');
echo "Today: " . testDate($today) . " (expected: ALLOWED)\n";
echo "+2 weeks: " . testDate($today->copy()->addWeeks(2)) . " (expected: ALLOWED)\n";
echo "+1 month: " . testDate($today->copy()->addMonth()) . " (expected: ALLOWED)\n";
echo "+35 days: " . testDate($today->copy()->addDays(35)) . " (expected: BLOCKED)\n";
echo "+2 months: " . testDate($today->copy()->addMonths(2)) . " (expected: BLOCKED)\n";
echo "Yesterday: " . testDate(Carbon::yesterday()) . " (expected: BLOCKED)\n";
echo "Message: " . BookingAvailabilityService::getRejectionMessage() . "\n\n";

// ─── TEST 3: Rolling 45 Days ─────────────────────────────────
echo "--- TEST 3: ROLLING Restriction (45 days) ---\n";
saveSetting('booking_advance_months', '0');
saveSetting('booking_advance_days', '45');
echo "+30 days: " . testDate($today->copy()->addDays(30)) . " (expected: ALLOWED)\n";
echo "+45 days: " . testDate($today->copy()->addDays(45)) . " (expected: ALLOWED)\n";
echo "+46 days: " . testDate($today->copy()->addDays(46)) . " (expected: BLOCKED)\n";
echo "+2 months: " . testDate($today->copy()->addMonths(2)) . " (expected: BLOCKED)\n\n";

// ─── TEST 4: Date Range ───────────────────────────────────────
echo "--- TEST 4: DATE RANGE ---\n";
$start = $today->copy()->addDays(5)->toDateString();
$end   = $today->copy()->addDays(20)->toDateString();
saveSetting('booking_window_type', 'date_range');
saveSetting('booking_start_date', $start);
saveSetting('booking_end_date', $end);
echo "Range: {$start} to {$end}\n";
echo "Today: " . testDate($today) . " (expected: BLOCKED - before range)\n";
echo "+3 days: " . testDate($today->copy()->addDays(3)) . " (expected: BLOCKED)\n";
echo "+5 days: " . testDate($today->copy()->addDays(5)) . " (expected: ALLOWED)\n";
echo "+10 days: " . testDate($today->copy()->addDays(10)) . " (expected: ALLOWED)\n";
echo "+20 days: " . testDate($today->copy()->addDays(20)) . " (expected: ALLOWED)\n";
echo "+21 days: " . testDate($today->copy()->addDays(21)) . " (expected: BLOCKED)\n\n";

// ─── TEST 5: API Payload ──────────────────────────────────────
echo "--- TEST 5: API Config Payload ---\n";
$payload = BookingAvailabilityService::getConfigurationPayload();
foreach ($payload as $k => $v) {
    echo sprintf("  %-25s: %s\n", $k, is_bool($v) ? ($v ? 'true' : 'false') : $v);
}

// ─── CLEANUP ──────────────────────────────────────────────────
echo "\n--- CLEANUP ---\n";
Setting::where('name', 'booking_window_restriction')->update(['val' => '0']);
Setting::where('name', 'booking_start_date')->update(['val' => '']);
Setting::where('name', 'booking_end_date')->update(['val' => '']);
echo "Restriction reset to OFF.\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "  TESTS COMPLETE\n";
echo str_repeat('=', 60) . "\n\n";
