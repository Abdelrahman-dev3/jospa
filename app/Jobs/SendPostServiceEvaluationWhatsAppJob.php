<?php

namespace App\Jobs;

use App\Services\Review\BookingReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;

/**
 * Sends the post-service review invitation (template with the "قيّمي تجربتكِ الآن" button).
 * Safe to dispatch more than once per booking: the service sends the invitation only once,
 * and a failed WhatsApp send is retried without creating a second review.
 */
class SendPostServiceEvaluationWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $bookingId;

    public function __construct($bookingId)
    {
        $this->bookingId = $bookingId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(BookingReviewService $reviews)
    {
        $booking = Booking::with('user')->find($this->bookingId);

        if (! $booking) {
            return;
        }

        $review = $reviews->sendRequest($booking);

        if ($review) {
            Log::info('WhatsApp review request processed.', [
                'booking_id' => $this->bookingId,
                'review_id' => $review->id,
                'status' => $review->status,
            ]);
        }
    }
}
