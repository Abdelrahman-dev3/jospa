<?php

namespace Tests\Unit;

use App\Jobs\SendNewBookingWhatsAppJob;
use App\Jobs\SendPostServiceEvaluationWhatsAppJob;
use Illuminate\Support\Facades\Queue;
use Modules\Booking\Models\Booking;
use Tests\TestCase;

class BookingWhatsAppJobsTest extends TestCase
{
    public function test_evaluation_job_is_dispatched_with_delay_when_booking_status_changes_to_checkout(): void
    {
        Queue::fake();

        $booking = new Booking();
        $booking->status = 'check_in';
        $booking->syncOriginal(); // Simulate an existing saved booking with status 'check_in'

        // Change status to checkout
        $booking->status = 'checkout';

        // Fire the updated event
        event("eloquent.updated: " . Booking::class, $booking);

        Queue::assertPushed(SendPostServiceEvaluationWhatsAppJob::class, function ($job) use ($booking) {
            return $job->bookingId === $booking->id && $job->delay !== null;
        });
    }

    public function test_evaluation_job_is_dispatched_with_delay_when_booking_status_changes_to_check_out_with_underscore(): void
    {
        Queue::fake();

        $booking = new Booking();
        $booking->status = 'check_in';
        $booking->syncOriginal();

        $booking->status = 'check_out';

        event("eloquent.updated: " . Booking::class, $booking);

        Queue::assertPushed(SendPostServiceEvaluationWhatsAppJob::class, function ($job) use ($booking) {
            return $job->bookingId === $booking->id && $job->delay !== null;
        });
    }

    public function test_evaluation_job_is_not_dispatched_when_status_changes_to_confirmed(): void
    {
        Queue::fake();

        $booking = new Booking();
        $booking->status = 'pending';
        $booking->syncOriginal();

        $booking->status = 'confirmed';

        event("eloquent.updated: " . Booking::class, $booking);

        Queue::assertNotPushed(SendPostServiceEvaluationWhatsAppJob::class);
    }
}
