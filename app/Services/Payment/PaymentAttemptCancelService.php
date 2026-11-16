<?php

namespace App\Services\Payment;

use App\Models\PaymentAttempt;
use App\Services\HyperpayService;
use App\Services\Payment\Strategies\CardPaymentStrategy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cancels a cart/checkout payment attempt the user abandoned in the app
 * (back button / closed the gateway WebView before the result page).
 *
 * Never cancels blindly:
 *  - HyperPay (card): asks HyperPay first. A completed payment is finalized through
 *    the normal callback flow instead of being cancelled; a payment still being
 *    processed is left pending.
 *  - UrPay: has no instant lookup here, so the attempt is marked cancelled with
 *    USER_CANCELLED_NOTE, and the urpay:reconcile-pending job keeps checking such
 *    attempts with the bank and finalizes them if they turn out to be paid.
 */
class PaymentAttemptCancelService
{
    public const USER_CANCELLED_NOTE = 'Cancelled by user (left the payment page).';

    public const RESULT_CANCELLED = 'cancelled';
    public const RESULT_ALREADY_FINAL = 'already_final';
    public const RESULT_IN_PROGRESS = 'in_progress';

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
        if (! $this->isOpen($attempt)) {
            return [$attempt, self::RESULT_ALREADY_FINAL];
        }

        if ($attempt->gateway === 'hyperpay' && $attempt->gateway_checkout_id) {
            $outcome = $this->checkHyperpay($attempt, $request);
            $attempt->refresh();

            if (! $this->isOpen($attempt)) {
                return [$attempt, self::RESULT_ALREADY_FINAL];
            }

            // Paid at HyperPay but not finalized yet, or still processing: never cancel.
            if ($outcome === 'paid' || $outcome === 'pending') {
                return [$attempt, self::RESULT_IN_PROGRESS];
            }
        }

        $this->attempts->markCancelled($attempt->id, self::USER_CANCELLED_NOTE);

        return [$attempt->refresh(), self::RESULT_CANCELLED];
    }

    private function isOpen(PaymentAttempt $attempt): bool
    {
        return in_array($attempt->status, [PaymentAttempt::STATUS_INITIATED, PaymentAttempt::STATUS_PENDING], true);
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

        if (preg_match('/^(000\.200|800\.400\.5|100\.400\.500)/', $code) === 1) {
            return 'pending';
        }

        return 'none';
    }
}
