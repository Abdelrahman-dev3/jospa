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
        return $this->update($attemptId, array_merge($attributes, [
            'status' => PaymentAttempt::STATUS_PAID,
            'paid_at' => $attributes['paid_at'] ?? now(),
            'error_message' => null,
        ]));
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
