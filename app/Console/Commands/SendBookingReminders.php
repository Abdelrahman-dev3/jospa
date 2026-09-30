<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Booking\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendBookingReminderWhatsAppJob;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminder to customers 6 hours before their booking';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Checking for bookings that need reminders...');

        // Find bookings starting between 5h45m and 6h15m from now
        // This 30-minute window ensures the every-15-minute scheduler catches them exactly once
        $from = Carbon::now()->addHours(5)->addMinutes(45);
        $to   = Carbon::now()->addHours(6)->addMinutes(15);

        $bookings = Booking::with('user')
            ->whereNotIn('status', ['cancelled', 'canceled', 'completed'])
            ->whereBetween('start_date_time', [$from, $to])
            ->whereNull('reminder_sent_at')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings need reminders right now.');
            return Command::SUCCESS;
        }

        $sentCount = 0;

        foreach ($bookings as $booking) {
            try {
                SendBookingReminderWhatsAppJob::dispatch($booking->id);

                // Mark as sent so we don't send duplicate reminders
                $booking->update(['reminder_sent_at' => now()]);

                $sentCount++;
                $this->info("Dispatched reminder for booking #{$booking->id}");
            } catch (\Exception $e) {
                Log::error("Failed to dispatch booking reminder for #{$booking->id}: " . $e->getMessage());
                $this->error("Error with booking #{$booking->id}");
            }
        }

        $this->info("Finished. Sent {$sentCount} reminder(s).");
        return Command::SUCCESS;
    }
}
