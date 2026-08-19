<?php

namespace Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CancelPendingBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:cancel-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel and delete unpaid pending bookings created from the website/cart after 2 hours (skips bookings with active payment attempts)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Checking for pending website bookings to cancel...');

        // Find bookings that:
        // - status is 'pending'
        // - payment_type is 'cart' or 'payment' (bookings created via frontend/cart)
        // - unpaid (no successful transaction)
        // - created_at is older than 10 minutes ago
        // - do NOT have an active payment_attempt (prevents deleting mid-payment bookings)
        $tenMinutesAgo = Carbon::now()->subMinutes(10);

        $bookings = Booking::where('status', 'pending')
            ->whereIn('payment_type', ['cart', 'payment'])
            ->where('created_at', '<=', $tenMinutesAgo)
            ->unpaid()
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payment_attempts')
                    ->whereColumn('payment_attempts.user_id', 'bookings.user_id')
                    ->whereIn('payment_attempts.status', ['pending', 'processing'])
                    ->where('payment_attempts.created_at', '>=', now()->subHours(2));
            })
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings found to cancel.');
            return Command::SUCCESS;
        }

        foreach ($bookings as $booking) {
            try {
                // Delete related records
                $booking->bookingService()->delete();
                $booking->packages()->delete();
                $booking->products()->delete();
                $booking->transactions()->delete();
                $booking->userCouponRedeem()->delete();

                // Delete the booking itself (standard soft delete)
                $booking->delete();

                Log::info("Auto-cancelled and deleted pending booking #{$booking->id} due to payment timeout (2 hours).");
                $this->info("Cancelled booking #{$booking->id}");
            } catch (\Exception $e) {
                Log::error("Failed to delete booking #{$booking->id}: " . $e->getMessage());
                $this->error("Error cancelling booking #{$booking->id}");
            }
        }

        $this->info('Finished cancelling pending bookings.');
        return Command::SUCCESS;
    }
}
