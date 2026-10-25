<?php

namespace App\Jobs;

use App\Models\BookingReview;
use App\Services\Review\BookingReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sends the "review received" WhatsApp message; retried on failure without side effects. */
class SendReviewConfirmationWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function __construct(public int $reviewId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(BookingReviewService $reviews)
    {
        $review = BookingReview::with('user')->find($this->reviewId);

        if ($review && ! $reviews->sendConfirmation($review)) {
            throw new \RuntimeException("Review confirmation WhatsApp failed for review #{$this->reviewId}.");
        }
    }
}
