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
            $booking->user->first_name ?? $booking->user->full_name ?? 'العميل',
            $bookingDate,
            $bookingTime
        ];

        // Replace 'jospa_appointment_confirmation' with the actual template name in Javna if different
        // If you don't use templates and just want to send text, you can use sendText() instead
        $templateName = 'jospa_appointment_confirmation';
        
        $whatsAppService->sendTemplate($phone, $variables, $templateName, 'ar');
    }
}
