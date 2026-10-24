<?php

namespace Modules\Booking\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingPaymentLink;
use Modules\Booking\Services\BookingPaymentLinkService;

class BookingPaymentLinkController extends Controller
{
    public function __construct(private BookingPaymentLinkService $links)
    {
        $this->middleware(['permission:view_booking'])->only(['show', 'refresh']);
        $this->middleware(['permission:add_booking'])->only(['store']);
    }

    /** Create (or return the active) payment link for a booking. */
    public function store($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        try {
            $link = $this->links->createForBooking($booking, auth()->id());
        } catch (\DomainException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => true,
            'message' => __('booking.payment_link_created'),
            'data' => $this->present($booking, $link),
        ]);
    }

    /** Current payment state of a booking and its latest link. */
    public function show($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $link = BookingPaymentLink::where('booking_id', $booking->id)->latest('id')->first();

        return response()->json(['status' => true, 'data' => $this->present($booking, $link)]);
    }

    /** Ask the gateway for the real payment state (for customers who paid but never returned). */
    public function refresh($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        $link = BookingPaymentLink::where('booking_id', $booking->id)->latest('id')->first();

        if ($link) {
            $this->links->reconcile($link);
            $link = $link->fresh();
        }

        return response()->json(['status' => true, 'data' => $this->present($booking, $link)]);
    }

    private function present(Booking $booking, ?BookingPaymentLink $link): array
    {
        $paid = $booking->transactions()->where('payment_status', 1)->exists();

        return [
            'booking_id' => $booking->id,
            'payment_state' => $paid ? 'paid' : 'pending',
            'link_status' => $link ? $this->links->effectiveStatus($link) : null,
            'url' => $link && $link->isUsable() ? $link->publicUrl() : null,
            'amount' => $link?->amount,
            'expires_at' => $link?->expires_at?->toDateTimeString(),
            'paid_at' => $link?->paid_at?->toDateTimeString(),
        ];
    }
}
