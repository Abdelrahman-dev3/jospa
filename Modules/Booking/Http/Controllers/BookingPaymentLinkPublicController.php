<?php

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Booking\Models\BookingPaymentLink;
use Modules\Booking\Services\BookingPaymentLinkService;

/**
 * Customer-facing side of a dashboard payment link. Opening or returning to this
 * page never marks anything as paid: only the gateway-verified callback does.
 */
class BookingPaymentLinkPublicController extends Controller
{
    public function __construct(private BookingPaymentLinkService $links)
    {
    }

    public function show(string $token)
    {
        $link = $this->findLink($token);
        $status = $this->links->effectiveStatus($link);

        // Customer may have just come back from the gateway before the callback landed.
        if ($status === BookingPaymentLink::STATUS_PENDING) {
            $status = $this->links->reconcile($link);
        }

        $booking = $link->booking()->with(['user', 'branch', 'services'])->first();

        return view('booking::frontend.payment-link', [
            'link' => $link,
            'booking' => $booking,
            'status' => $status,
            'customerName' => trim(($booking->user->first_name ?? '') . ' ' . ($booking->user->last_name ?? '')),
            'branchName' => $booking->branch->name ?? null,
            'serviceNames' => $booking->services->pluck('service_name')->filter()->implode('، '),
        ]);
    }

    public function pay(Request $request, string $token)
    {
        $link = $this->findLink($token);

        $data = $request->validate(['brand' => 'nullable|in:VISA,MADA']);

        try {
            $checkoutUrl = $this->links->startCheckout($link, $data['brand'] ?? 'VISA');
        } catch (\DomainException $e) {
            return redirect()->route('booking-payment-link.show', $token)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('booking-payment-link.show', $token)
                ->with('error', __('booking.payment_link_gateway_error'));
        }

        return redirect()->away($checkoutUrl);
    }

    private function findLink(string $token): BookingPaymentLink
    {
        return BookingPaymentLink::where('token', $token)->with('booking')->firstOrFail();
    }
}
