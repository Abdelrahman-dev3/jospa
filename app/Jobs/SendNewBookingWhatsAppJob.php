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

class SendNewBookingWhatsAppJob implements ShouldQueue
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
            return;
        }

        $phone = $booking->user->mobile;
        
        $bookingDate = Carbon::parse($booking->start_date_time)->format('Y-m-d');
        $bookingTime = Carbon::parse($booking->start_date_time)->format('h:i A');

        // Pass variables expected by the template. Update the template name and variables as needed.
        $variables = [
            $booking->user->first_name ?? $booking->user->full_name ?? 'عزيزي العميل',
            $bookingDate,
            $bookingTime
        ];

        $templateName = 'jospa_appointment_confirmation';
        
        $isSent = $whatsAppService->sendTemplate($phone, $variables, $templateName, 'ar');

        if ($isSent) {
            \Illuminate\Support\Facades\Log::info("WhatsApp booking notification sent successfully.", [
                'booking_id' => $this->bookingId,
                'phone' => $phone,
                'template' => $templateName
            ]);
        } else {
            \Illuminate\Support\Facades\Log::error("Failed to send WhatsApp booking notification.", [
                'booking_id' => $this->bookingId,
                'phone' => $phone,
                'template' => $templateName
            ]);
        }
    }
}
