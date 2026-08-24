<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Booking\Models\Booking;
use App\Services\WhatsApp\JavnaWhatsAppService;

class SendPostServiceEvaluationWhatsAppJob implements ShouldQueue
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
        
        // Pass any variables the template expects (like customer name). Empty array if none.
        $variables = [
            $booking->user->first_name ?? $booking->user->full_name ?? 'العميل'
        ];

        // The template name exactly as requested by user
        $templateName = 'Post_service _evaluation';
        
        $isSent = $whatsAppService->sendTemplate($phone, $variables, $templateName, 'ar');

        if ($isSent) {
            \Illuminate\Support\Facades\Log::info("WhatsApp post-service evaluation notification sent successfully.", [
                'booking_id' => $this->bookingId,
                'phone' => $phone,
                'template' => $templateName
            ]);
        } else {
            \Illuminate\Support\Facades\Log::error("Failed to send WhatsApp post-service evaluation notification.", [
                'booking_id' => $this->bookingId,
                'phone' => $phone,
                'template' => $templateName
            ]);
        }
    }
}
