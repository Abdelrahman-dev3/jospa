<?php

namespace App\Http\Controllers;

use App\Models\BookingReview;
use App\Services\Review\BookingReviewService;
use App\Services\Review\ReviewQuestions;
use Illuminate\Http\Request;

/**
 * Website review form, opened with the booking-specific token. Visiting the page never
 * grants anything: only a completed submission is saved (once) and rewarded.
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
            'review' => $review,
            'questions' => ReviewQuestions::all(),
            'state' => $this->state($review),
            'points' => app(BookingReviewService::class)->pointsPerReview(),
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

    private function state(BookingReview $review): string
    {
        if ($review->isCompleted()) {
            return 'done';
        }

        return $review->isAnswerable() ? 'open' : 'expired';
    }
}
