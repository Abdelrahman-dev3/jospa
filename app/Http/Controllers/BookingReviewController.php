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
