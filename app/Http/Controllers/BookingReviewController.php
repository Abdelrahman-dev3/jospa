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

        // Award cashback (idempotent — safe if clicked multiple times)
        if ($review->isCompleted() && ! $review->google_maps_clicked_at) {
            $service->confirmGoogleReview($review);
        }

        // Always redirect to Google Maps so the customer can leave their review
        return redirect()->away(
            'https://www.google.com/maps/place/%D8%AC%D9%88+%D8%B3%D8%A8%D8%A7%E2%80%AD/@24.7501491,46.6261178,16.93z/data=!4m8!3m7!1s0x3e2ee3b0cf2cf4b3:0xd9fc022701b9d604!8m2!3d24.7501398!4d46.6236843!9m1!1b1!16s%2Fg%2F11shgfzh4g?entry=ttu&g_ep=EgoyMDI2MTAyOC4wIKXMDSoASAFQAw%3D%3D'
        );
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
