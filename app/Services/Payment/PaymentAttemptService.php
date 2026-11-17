<?php

namespace App\Services\Payment;

use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

class PaymentAttemptService
{
    public function create(array $paymentData, float $amount, string $gateway, Request $request): PaymentAttempt
    {
        return PaymentAttempt::create([
            'user_id' => $paymentData['user_id'] ?? auth()->id(),
            'gateway' => $gateway,
            'payment_method' => $paymentData['payment_method'] ?? $gateway,
            'page' => $paymentData['page'] ?? 'cart',
            'status' => PaymentAttempt::STATUS_INITIATED,
            'currency' => 'SAR',
            'amount' => $amount,
            'discount_amount' => (float) ($paymentData['discountAmount'] ?? 0),
            'cart_ids' => $paymentData['cart_ids'] ?? [],
            'gift_ids' => $paymentData['gift_ids'] ?? [],
            'coupon_code' => $paymentData['couponCode'] ?? null,
            'gift_code' => data_get($paymentData, 'submethods.gift_code'),
            'wallet_used' => (bool) data_get($paymentData, 'submethods.wallet', false),
            'loyalty_used' => (bool) data_get($paymentData, 'submethods.loyalty', false),
            'gateway_response' => null,
            'callback_payload' => null,
            'error_message' => null,
        ]);
    }

    public function update(?int $attemptId, array $attributes = []): ?PaymentAttempt
    {
        if (! $attemptId) {
            return null;
        }

        $attempt = PaymentAttempt::find($attemptId);
        if (! $attempt) {
            return null;
        }

        if (array_key_exists('gateway_response', $attributes) && is_array($attempt->gateway_response) && is_array($attributes['gateway_response'])) {
            $attributes['gateway_response'] = array_merge($attempt->gateway_response, $attributes['gateway_response']);
        }

        if (array_key_exists('callback_payload', $attributes) && is_array($attempt->callback_payload) && is_array($attributes['callback_payload'])) {
            $attributes['callback_payload'] = array_merge($attempt->callback_payload, $attributes['callback_payload']);
        }

        $attempt->fill($attributes);
        $attempt->save();

        return $attempt->fresh(['user', 'invoice']);
    }

    public function markPending(?int $attemptId, array $attributes = []): ?PaymentAttempt
    {
        return $this->update($attemptId, array_merge($attributes, [
            'status' => PaymentAttempt::STATUS_PENDING,
        ]));
    }

    public function markPaid(?int $attemptId, array $attributes = []): ?PaymentAttempt
    {
        $attempt = $this->update($attemptId, array_merge($attributes, [
            'status' => PaymentAttempt::STATUS_PAID,
            'paid_at' => $attributes['paid_at'] ?? now(),
            'error_message' => null,
        ]));

        if ($attempt) {
            $this->flagDuplicateCapture($attempt);
        }

        // Keep dashboard payment links in sync once the gateway-confirmed payment is recorded.
        if ($attempt && $attempt->page === 'payment') {
            try {
                app(\Modules\Booking\Services\BookingPaymentLinkService::class)->markPaidFromAttempt($attempt);
            } catch (\Throwable $e) {
                \Log::warning('Failed to sync booking payment link after payment.', [
                    'attempt_id' => $attempt->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $attempt;
    }

    /**
     * Two different gateway payments for the same cart items = the customer was charged
     * twice. The order is only finalized once, so the extra charge must be refunded
     * manually: log it loudly for the finance team.
     */
    private function flagDuplicateCapture(PaymentAttempt $attempt): void
    {
        $items = array_merge(
            array_map(fn ($id) => 'c' . $id, (array) ($attempt->cart_ids ?? [])),
            array_map(fn ($id) => 'g' . $id, (array) ($attempt->gift_ids ?? []))
        );

        if (empty($items)) {
            return;
        }

        $others = PaymentAttempt::where('user_id', $attempt->user_id)
            ->where('id', '!=', $attempt->id)
            ->where('status', PaymentAttempt::STATUS_PAID)
            ->where('created_at', '>=', now()->subDays(2))
            ->get();

        foreach ($others as $other) {
            $otherItems = array_merge(
                array_map(fn ($id) => 'c' . $id, (array) ($other->cart_ids ?? [])),
                array_map(fn ($id) => 'g' . $id, (array) ($other->gift_ids ?? []))
            );

            $sameTransaction = $attempt->gateway_transaction_id
                && $attempt->gateway_transaction_id === $other->gateway_transaction_id;

            if (! $sameTransaction && array_intersect($items, $otherItems)) {
                \Log::critical('DUPLICATE PAYMENT: the same cart items were paid twice. Refund the later charge.', [
                    'user_id' => $attempt->user_id,
                    'attempt_id' => $attempt->id,
                    'attempt_amount' => $attempt->amount,
                    'attempt_gateway' => $attempt->gateway,
                    'attempt_transaction_id' => $attempt->gateway_transaction_id,
                    'other_attempt_id' => $other->id,
                    'other_transaction_id' => $other->gateway_transaction_id,
                ]);
            }
        }
    }

    public function markFailed(?int $attemptId, ?string $message = null, array $attributes = []): ?PaymentAttempt
    {
        return $this->update($attemptId, array_merge($attributes, [
            'status' => PaymentAttempt::STATUS_FAILED,
            'error_message' => $message,
        ]));
    }

    public function markCancelled(?int $attemptId, ?string $message = null, array $attributes = []): ?PaymentAttempt
    {
        return $this->update($attemptId, array_merge($attributes, [
            'status' => PaymentAttempt::STATUS_CANCELLED,
            'error_message' => $message,
        ]));
    }
}
