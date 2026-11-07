<?php

namespace App\Http\Controllers;

use App\Models\BookingReview;
use App\Services\Review\BookingReviewService;
use App\Services\Review\ReviewQuestions;
use Illuminate\Http\Request;

/**
 * Website review form, opened with the booking-specific token. Visiting the page never
 * grants anything: only a completed submission is saved (once) and rewarded after
 * the customer clicks the Google Maps review link.
 */
class BookingReviewController extends Controller
{
    /**
     * Entry point from the customer account: only the owner of a completed, paid booking
     * can open (and lazily create) the review for it.
     */
    public function start(int $bookingId, BookingReviewService $service)
    {
        $booking = \Modules\Booking\Models\Booking::getCompletedBookings(auth()->id())->firstWhere('id', $bookingId);

        abort_unless($booking, 404);

        $review = $service->firstOrCreateForBooking($booking);

        return redirect()->route('booking-review.show', $review->token);
    }

    public function show(string $token)
    {
        $review = BookingReview::where('token', $token)->firstOrFail();

        return view('frontend.booking-review', [
            'review'    => $review,
            'questions' => ReviewQuestions::all(),
            'state'     => $this->state($review),
            'cashback'  => app(BookingReviewService::class)->cashbackAmount(),
        ]);
    }

    public function store(Request $request, string $token, BookingReviewService $service)
    {
        $review = BookingReview::where('token', $token)->firstOrFail();

        if ($this->state($review) !== 'open') {
            return redirect()->route('booking-review.show', $token);
        }

        $rules = [];
        foreach (ReviewQuestions::all() as $question) {
            $rules[$question['field']] = 'required|integer|between:1,4';
        }
        $answers = $request->validate($rules);

        $service->submitFromWebsite($review, $answers);

        return redirect()->route('booking-review.show', $token);
    }

    /**
     * The customer clicked the Google Maps confirmation link.
     * Awards cashback once, then redirects to Google Maps.
     */
    public function googleConfirm(string $token, BookingReviewService $service)
    {
        $review = BookingReview::where('token', $token)->firstOrFail();

        // Award cashback (idempotent — safe if clicked multiple times). A failure here must
        // never block the customer from reaching Google Maps.
        try {
            if ($review->isCompleted() && ! $review->google_maps_clicked_at) {
                $service->confirmGoogleReview($review);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Review Google confirm failed.', [
                'review_id' => $review->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Always redirect to Google Maps so the customer can leave their review
        return redirect()->away($service->googleMapsUrl($review));
    }

    private function state(BookingReview $review): string
    {
        if ($review->isCompleted()) {
            // If google maps already clicked => fully done
            if ($review->google_maps_clicked_at) {
                return 'done';
            }
            // Questions answered but waiting for google maps click
            return 'pending_google';
        }

        return $review->isAnswerable() ? 'open' : 'expired';
    }
}
