<?php

namespace App\Services\Payment;

use App\Models\PaymentAttempt;
use App\Services\HyperpayService;
use App\Services\Payment\Strategies\CardPaymentStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Cancels a cart/checkout payment attempt the user abandoned (back button / closed the
 * gateway page), and guards against starting a second payment while one may still be paid.
 *
 * Nothing is cancelled blindly: the gateway is asked first.
 *  - HyperPay (card): a completed payment is finalized through the normal callback flow;
 *    a payment still processing is left open; otherwise the attempt is cancelled.
 *  - UrPay: the bank is queried now (urpay:reconcile-pending --attempt=ID). Paid is
 *    finalized, cancel/failure is recorded; no answer from the bank leaves it open.
 *
 * So once an attempt is cancelled/failed it is final, and a new payment is safe.
 */
class PaymentAttemptCancelService
{
    public const USER_CANCELLED_NOTE = 'Cancelled by user (left the payment page).';

    public const RESULT_CANCELLED = 'cancelled';
    public const RESULT_ALREADY_FINAL = 'already_final';
    public const RESULT_IN_PROGRESS = 'in_progress';

    /** Open attempts younger than this are checked before a new payment may start. */
    private const GUARD_WINDOW_HOURS = 2;

    public function __construct(
        private HyperpayService $hyperpay,
        private PaymentAttemptService $attempts
    ) {
    }

    /**
     * @return array{0: PaymentAttempt, 1: string}  [fresh attempt, one of the RESULT_* constants]
     */
    public function cancelByUser(PaymentAttempt $attempt, Request $request): array
    {
        if (! self::isOpen($attempt)) {
            return [$attempt, self::RESULT_ALREADY_FINAL];
        }

        if ($attempt->gateway === 'hyperpay') {
            $outcome = $attempt->gateway_checkout_id ? $this->checkHyperpay($attempt, $request) : 'none';
            $attempt->refresh();

            if (! self::isOpen($attempt)) {
                return [$attempt, self::RESULT_ALREADY_FINAL];
            }

            // Paid at HyperPay but not finalized yet, or still processing: never cancel.
            if ($outcome === 'paid' || $outcome === 'pending') {
                return [$attempt, self::RESULT_IN_PROGRESS];
            }

            $this->attempts->markCancelled($attempt->id, self::USER_CANCELLED_NOTE);

            return [$attempt->refresh(), self::RESULT_CANCELLED];
        }

        if (in_array($attempt->gateway, ['urpay', 'applepay_urpay'], true)) {
            $this->checkUrPay($attempt);
            $attempt->refresh();

            if (self::isOpen($attempt)) {
                // The bank gave no definitive answer: do not cancel, the app keeps polling.
                return [$attempt, self::RESULT_IN_PROGRESS];
            }

            return [$attempt, $attempt->status === PaymentAttempt::STATUS_PAID ? self::RESULT_ALREADY_FINAL : self::RESULT_CANCELLED];
        }

        // Other gateways: no lookup available here.
        $this->attempts->markCancelled($attempt->id, self::USER_CANCELLED_NOTE);

        return [$attempt->refresh(), self::RESULT_CANCELLED];
    }

    /**
     * Run before creating a new payment for the user. Resolves the user's recent open
     * attempts with their gateways (cancelling abandoned ones).
     *
     * @return PaymentAttempt|null  An attempt that blocks a new payment (paid, or still
     *                              processing), or null when a new payment is safe.
     */
    public function blockingAttemptFor(int $userId, Request $request): ?PaymentAttempt
    {
        // A card attempt the user cancelled may still have been paid right after: check it.
        $cancelledCard = PaymentAttempt::where('user_id', $userId)
            ->where('gateway', 'hyperpay')
            ->where('status', PaymentAttempt::STATUS_CANCELLED)
            ->where('error_message', self::USER_CANCELLED_NOTE)
            ->whereNotNull('gateway_checkout_id')
            ->where('created_at', '>=', now()->subHours(self::GUARD_WINDOW_HOURS))
            ->get();

        foreach ($cancelledCard as $attempt) {
            if ($this->checkHyperpay($attempt, $request) === 'paid') {
                return $attempt->refresh();
            }
        }

        $open = PaymentAttempt::where('user_id', $userId)
            ->whereIn('status', [PaymentAttempt::STATUS_INITIATED, PaymentAttempt::STATUS_PENDING])
            ->where('created_at', '>=', now()->subHours(self::GUARD_WINDOW_HOURS))
            ->orderBy('id')
            ->get();

        foreach ($open as $attempt) {
            [$attempt, $result] = $this->cancelByUser($attempt, $request);

            if ($result === self::RESULT_IN_PROGRESS
                || ($result === self::RESULT_ALREADY_FINAL && $attempt->status === PaymentAttempt::STATUS_PAID)) {
                return $attempt;
            }
        }

        return null;
    }

    public static function isOpen(PaymentAttempt $attempt): bool
    {
        return in_array($attempt->status, [PaymentAttempt::STATUS_INITIATED, PaymentAttempt::STATUS_PENDING], true);
    }

    /** True when the attempt is final and not paid: starting a new payment is safe. */
    public static function canRetry(PaymentAttempt $attempt): bool
    {
        return in_array($attempt->status, [PaymentAttempt::STATUS_FAILED, PaymentAttempt::STATUS_CANCELLED], true);
    }

    private function checkUrPay(PaymentAttempt $attempt): void
    {
        try {
            Artisan::call('urpay:reconcile-pending', ['--attempt' => $attempt->id, '--minutes' => 0]);
        } catch (\Throwable $e) {
            Log::error('Payment cancel: UrPay bank lookup failed.', [
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return string paid | pending | none
     */
    private function checkHyperpay(PaymentAttempt $attempt, Request $request): string
    {
        $resourcePath = '/v1/checkouts/' . rawurlencode((string) $attempt->gateway_checkout_id) . '/payment';
        $brand = strtoupper((string) (data_get($attempt->gateway_response, 'payment_context.hyperpay_brand')
            ?? data_get($attempt->gateway_response, 'hyperpay_brand')
            ?? 'VISA'));

        $payment = null;
        foreach (array_unique([$brand, $brand === 'MADA' ? 'VISA' : 'MADA']) as $tryBrand) {
            try {
                $payment = $this->hyperpay->fetchPaymentStatus($resourcePath, $tryBrand);
                break;
            } catch (\Throwable $e) {
                // HyperPay answers with an error while no payment exists for the checkout.
                $payment = null;
            }
        }

        if (! is_array($payment)) {
            return 'none';
        }

        $code = (string) data_get($payment, 'result.code', '');

        if ($this->hyperpay->isSuccessfulResult($code) && ! $this->hyperpay->isTestModeResult($code)) {
            // Finalize through the normal, server-verified callback flow (books the cart,
            // marks the attempt paid). It re-fetches the payment from HyperPay itself.
            try {
                app(CardPaymentStrategy::class)->callbackPlain($request->duplicate([
                    'resourcePath' => $resourcePath,
                    'mtid' => (string) $attempt->merchant_reference,
                    'brand' => $brand,
                ], []));
            } catch (\Throwable $e) {
                Log::error('Payment cancel: finalizing a paid HyperPay attempt failed.', [
                    'attempt_id' => $attempt->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Even if finalizing failed, never cancel a payment HyperPay reports as paid.
            return 'paid';
        }

        // 000.200.x = "transaction pending": also what HyperPay returns for an open checkout
        // the user never paid, so it does not block a cancel. A late payment is caught by
        // recheckCancelledHyperpay() (scheduled) and by the guard before a new payment.
        // Only these codes mean a real payment is waiting on the bank/acquirer.
        if (preg_match('/^(800\.400\.5|100\.400\.500)/', $code) === 1) {
            return 'pending';
        }

        return 'none';
    }

    /**
     * Re-checks card attempts the user cancelled in the last hours; a payment that
     * completed after the cancel is finalized (booking confirmed). Returns how many were paid.
     */
    public function recheckCancelledHyperpay(): int
    {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $paid = 0;

        $attempts = PaymentAttempt::where('gateway', 'hyperpay')
            ->where('status', PaymentAttempt::STATUS_CANCELLED)
            ->where('error_message', self::USER_CANCELLED_NOTE)
            ->whereNotNull('gateway_checkout_id')
            ->where('created_at', '>=', now()->subHours(self::GUARD_WINDOW_HOURS))
            ->orderBy('id')
            ->get();

        foreach ($attempts as $attempt) {
            if ($this->checkHyperpay($attempt, $request) === 'paid') {
                $paid++;
            }
        }

        return $paid;
    }
}
