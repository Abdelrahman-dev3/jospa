<?php

namespace Modules\Booking\Services;

use App\Models\PaymentAttempt;
use App\Services\HyperpayService;
use App\Services\Payment\PaymentAttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingPaymentLink;
use Modules\Booking\Models\BookingProduct;
use Modules\Booking\Models\BookingService;
use Modules\Package\Models\BookingPackages;

class BookingPaymentLinkService
{
    public const LINK_TTL_HOURS = 48;

    /**
     * Create (or reuse the active) payment link for an unpaid booking.
     * The amount is always computed server-side from the booking itself.
     */
    public function createForBooking(Booking $booking, ?int $createdBy = null): BookingPaymentLink
    {
        if ($booking->transactions()->where('payment_status', 1)->exists()) {
            throw new \DomainException(__('booking.payment_link_already_paid'));
        }

        $amounts = $this->calculateAmounts($booking);
        if ($amounts['total'] <= 0) {
            throw new \DomainException(__('booking.payment_link_no_amount'));
        }

        $existing = BookingPaymentLink::where('booking_id', $booking->id)
            ->where('status', BookingPaymentLink::STATUS_PENDING)
            ->latest('id')
            ->first();

        if ($existing && $existing->isUsable()) {
            if (abs($existing->amount - $amounts['total']) > 0.01) {
                $existing->update(['amount' => $amounts['total']]);
            }

            return $existing;
        }

        if ($existing) {
            $existing->update(['status' => BookingPaymentLink::STATUS_EXPIRED]);
        }

        return BookingPaymentLink::create([
            'booking_id' => $booking->id,
            'token' => Str::random(48),
            'amount' => $amounts['total'],
            'currency' => 'SAR',
            'status' => BookingPaymentLink::STATUS_PENDING,
            'created_by' => $createdBy,
            'expires_at' => now()->addHours(self::LINK_TTL_HOURS),
        ]);
    }

    /**
     * @return array{subtotal: float, tax: float, total: float}
     */
    public function calculateAmounts(Booking $booking): array
    {
        $services = BookingService::where('booking_id', $booking->id)->get();
        $servicesTotal = (float) $services->sum(fn ($s) => ($s->service_price ?? 0) - ($s->discount_amount ?? 0));
        $packagesTotal = (float) BookingPackages::where('booking_id', $booking->id)->sum('package_price');
        $productsTotal = (float) BookingProduct::where('booking_id', $booking->id)
            ->get()
            ->sum(fn ($p) => ($p->discounted_price ?: $p->product_price) * $p->product_qty);

        $taxableTotal = $servicesTotal + $packagesTotal;
        $tax = (float) getBookingTaxamount($taxableTotal, 0, null)['total_tax_amount']
            + ($productsTotal > 0 ? (float) getTaxamount($productsTotal)['total_tax_amount'] : 0);

        $subtotal = $taxableTotal + $productsTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round(max($subtotal + $tax, 0), 2),
        ];
    }

    /**
     * Effective status: a booking paid by any means closes the link.
     */
    public function effectiveStatus(BookingPaymentLink $link): string
    {
        if ($link->status === BookingPaymentLink::STATUS_PAID) {
            return BookingPaymentLink::STATUS_PAID;
        }

        if ($link->booking && $link->booking->transactions()->where('payment_status', 1)->exists()) {
            return BookingPaymentLink::STATUS_PAID;
        }

        return $link->isExpired() ? BookingPaymentLink::STATUS_EXPIRED : $link->status;
    }

    /**
     * Open a Hyperpay checkout for the link and return the hosted checkout URL.
     * Payment is only confirmed later, server-to-server, by the Hyperpay callback
     * verification (CardPaymentStrategy) or by the reconcile command.
     */
    public function startCheckout(BookingPaymentLink $link, string $brand = 'VISA'): string
    {
        $booking = $link->booking()->with('user')->first();
        if (! $booking || ! $booking->user) {
            throw new \RuntimeException(__('booking.payment_link_invalid'));
        }

        if ($this->effectiveStatus($link) !== BookingPaymentLink::STATUS_PENDING) {
            throw new \DomainException(__('booking.payment_link_not_payable'));
        }

        $brand = strtoupper($brand) === 'MADA' ? 'MADA' : 'VISA';
        $amounts = $this->calculateAmounts($booking);
        $amount = $amounts['total'];
        if ($amount <= 0) {
            throw new \DomainException(__('booking.payment_link_no_amount'));
        }
        if (abs($link->amount - $amount) > 0.01) {
            $link->update(['amount' => $amount]);
        }

        $paymentData = [
            'user_id' => (int) $booking->user_id,
            'page' => 'payment',
            'payment_method' => 'card',
            'couponCode' => '',
            'submethods' => ['wallet' => false, 'loyalty' => false, 'gift_code' => null],
            'final_before_sub' => $amount,
            'discountAmount' => 0,
            'couponDiscountAmount' => 0,
            'paymentGatewayDiscountAmount' => 0,
            'paymentGatewayDiscountMethod' => null,
            'paymentGatewayDiscountLabel' => null,
            'tax' => $amounts['tax'],
            'cart_ids' => [(int) $booking->id],
            'gift_ids' => [],
            'product_ids' => [],
            'amount' => $amount,
        ];

        $attemptService = app(PaymentAttemptService::class);
        $attempt = $attemptService->create($paymentData, $amount, 'hyperpay', new Request());
        $paymentData['attempt_id'] = $attempt->id;

        try {
            $mtid = Str::upper(Str::random(16));
            $checkout = app(HyperpayService::class)->createCheckout(
                $amount,
                $mtid,
                $this->shopperResultUrl($mtid, $brand),
                $this->customerData($booking),
                $brand
            );

            $checkoutId = (string) ($checkout['id'] ?? '');
            if ($checkoutId === '') {
                throw new \RuntimeException(__('booking.payment_link_gateway_error'));
            }

            $subResult = [
                'remaining_amount' => $amount,
                'used_wallet' => 0,
                'used_loyalty' => 0,
                'used_gift' => 0,
            ];

            // Same payment_context shape CardPaymentStrategy restores from, so the
            // standard Hyperpay callback can finalize this attempt without a session.
            $attemptService->markPending($attempt->id, [
                'merchant_reference' => $mtid,
                'gateway_checkout_id' => $checkoutId,
                'gateway_response' => [
                    'checkout' => $checkout,
                    'hyperpay_brand' => $brand,
                    'payment_context' => array_merge($paymentData, [
                        'subResult' => $subResult,
                        'checkout_id' => $checkoutId,
                        'merchant_transaction_id' => $mtid,
                        'hyperpay_brand' => $brand,
                        'booking_payment_link_id' => $link->id,
                    ]),
                ],
            ]);

            $link->update(['payment_attempt_id' => $attempt->id]);

            return URL::temporarySignedRoute('hyperpay.checkout', now()->addMinutes(30), [
                'checkoutId' => $checkoutId,
                'mtid' => $mtid,
            ]);
        } catch (\Throwable $e) {
            $attemptService->markFailed($attempt->id, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ask Hyperpay (server-to-server) whether the link's latest checkout was paid and,
     * only if the gateway confirms it, finalize through the standard callback path.
     * Covers customers who paid but never returned to the site. Returns the effective status.
     */
    public function reconcile(BookingPaymentLink $link): string
    {
        $link->loadMissing('booking');
        $attempt = $link->attempt;

        if ($this->effectiveStatus($link) === BookingPaymentLink::STATUS_PAID
            || ! $attempt
            || $attempt->status !== PaymentAttempt::STATUS_PENDING
            || ! $attempt->gateway_checkout_id
            || ! $attempt->merchant_reference
        ) {
            return $this->effectiveStatus($link);
        }

        $hyperpay = app(HyperpayService::class);
        $brand = (string) data_get($attempt->gateway_response, 'hyperpay_brand', 'VISA');
        $resourcePath = '/v1/checkouts/' . $attempt->gateway_checkout_id . '/payment';

        try {
            $payment = $hyperpay->fetchPaymentStatus($resourcePath, $brand);
        } catch (\Throwable $e) {
            // Not paid yet / session unknown to the gateway: leave the attempt untouched.
            return $this->effectiveStatus($link);
        }

        $resultCode = (string) data_get($payment, 'result.code', '');
        if (! $hyperpay->isSuccessfulResult($resultCode) || $hyperpay->isTestModeResult($resultCode)) {
            return $this->effectiveStatus($link);
        }

        // Re-verifies with the gateway, checks the amount and finalizes the payment.
        app(\App\Services\Payment\Strategies\CardPaymentStrategy::class)->callbackPlain(
            Request::create('/payment/hyperpay/result', 'GET', [
                'resourcePath' => $resourcePath,
                'mtid' => $attempt->merchant_reference,
                'brand' => $brand,
            ])
        );

        return $this->effectiveStatus($link->fresh(['booking']));
    }

    /**
     * Called when any payment attempt is confirmed as paid by the gateway.
     */
    public function markPaidFromAttempt(PaymentAttempt $attempt): void
    {
        $bookingIds = is_array($attempt->cart_ids) ? array_filter($attempt->cart_ids) : [];
        if (empty($bookingIds)) {
            return;
        }

        BookingPaymentLink::whereIn('booking_id', $bookingIds)
            ->whereIn('status', [BookingPaymentLink::STATUS_PENDING, BookingPaymentLink::STATUS_EXPIRED])
            ->update([
                'status' => BookingPaymentLink::STATUS_PAID,
                'invoice_id' => $attempt->invoice_id,
                'paid_at' => $attempt->paid_at ?? now(),
                'payment_attempt_id' => $attempt->id,
            ]);
    }

    private function shopperResultUrl(string $mtid, string $brand): string
    {
        $configured = rtrim((string) config('services.hyperpay.shopper_result_url', ''), '/');
        $params = ['mtid' => $mtid, 'brand' => $brand];

        if ($configured === '') {
            return route('hyperpay.callback.plain', $params);
        }

        return $configured . (str_contains($configured, '?') ? '&' : '?') . http_build_query($params);
    }

    private function customerData(Booking $booking): array
    {
        $user = $booking->user;
        $first = trim((string) ($user->first_name ?? ''));

        return [
            'given_name' => $first ?: 'Customer',
            'surname' => trim((string) ($user->last_name ?? '')) ?: ($first ?: 'Customer'),
            'mobile' => trim((string) ($user->mobile ?? '')),
            'email' => trim((string) ($user->email ?? '')),
            'address' => trim((string) ($user->address ?? '')),
            'city' => trim((string) ($user->city ?? '')),
            'country' => 'SA',
        ];
    }
}
