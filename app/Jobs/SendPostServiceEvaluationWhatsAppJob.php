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
        
        $whatsAppService->sendTemplate($phone, $variables, $templateName, 'ar');
    }
}
