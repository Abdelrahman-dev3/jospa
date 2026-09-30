<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Booking;
use App\Services\WhatsApp\JavnaWhatsAppService;
use Carbon\Carbon;

class SendBookingReminderWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $bookingId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($bookingId)
    {
        $this->bookingId = $bookingId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(JavnaWhatsAppService $whatsAppService)
    {
        $booking = Booking::with('user')->find($this->bookingId);

        if (!$booking || !$booking->user || empty($booking->user->mobile)) {
            \Illuminate\Support\Facades\Log::warning("WhatsApp Reminder Job: Missing booking, user, or mobile.", [
                'booking_id' => $this->bookingId,
            ]);
            return;
        }

        // Skip if booking was cancelled or completed before the reminder fires
        if (in_array($booking->status, ['cancelled', 'canceled', 'completed'])) {
            \Illuminate\Support\Facades\Log::info("WhatsApp Reminder Job: Skipping because booking status is {$booking->status}.", [
                'booking_id' => $this->bookingId,
            ]);
            return;
        }

        $phone = $booking->user->mobile;

        $bookingDate = Carbon::parse($booking->start_date_time)->format('Y-m-d');
        $bookingTime = Carbon::parse($booking->start_date_time)->format('h:i A');

        $variables = [
            $booking->user->first_name ?? $booking->user->full_name ?? 'عزيزي العميل',
            $bookingDate,
            $bookingTime
        ];

        $templateName = config('services.javna.whatsapp_booking_reminder_template_name', 'jospa_appointment_reminder');

        $isSent = $whatsAppService->sendTemplate($phone, $variables, $templateName, 'ar');

        if ($isSent) {
            \Illuminate\Support\Facades\Log::info("WhatsApp booking reminder sent successfully.", [
                'booking_id' => $this->bookingId,
                'phone' => $phone,
                'template' => $templateName
            ]);
        } else {
            \Illuminate\Support\Facades\Log::error("Failed to send WhatsApp booking reminder.", [
                'booking_id' => $this->bookingId,
                'phone' => $phone,
                'template' => $templateName
            ]);
        }
    }
}
