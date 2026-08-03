<?php

namespace App\Services\Payment\Strategies;

use App\Models\GiftCard;
use App\Models\PaymentAttempt;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentAttemptService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;

abstract class BasePaymentStrategy
{
    protected function preparePaymentFlow(Request $request, string $typePage, string $paymentMethod): array
    {
        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => $paymentMethod,
            'couponCode' => $request->invoiceCopon ?? $request->coupon_code ?? '',
            'submethods' => [
                'wallet' => (bool) $request->wallet,
                'loyalty' => (bool) $request->loyalty,
                'gift_code' => $request->gift_code,
                'gift_amount' => $request->gift_amount,
            ],
            'final_before_sub' => $request->total ?? $request->total_price ?? 0,
            'discountAmount' => $request->discountAmount ?? $request->discount_amount ?? 0,
            'couponDiscountAmount' => 0,
            'paymentGatewayDiscountAmount' => 0,
            'paymentGatewayDiscountMethod' => null,
            'paymentGatewayDiscountLabel' => null,
            'cart_ids' => [],
            'gift_ids' => [],
            'product_ids' => [],
        ];

        $calculator = app(PaymentCalculatorService::class);
        $totalData = $calculator->calculateTotal($typePage, $request->invoiceCopon, $paymentMethod);
        if (isset($totalData['error'])) {
            return [
                'response' => $this->respondPaymentInputError($request, $totalData['error']),
            ];
        }

        $data['final_before_sub'] = $totalData['total'];
        $data['discountAmount'] = $totalData['discountAmount'];
        $data['couponDiscountAmount'] = $totalData['couponDiscountAmount'] ?? 0;
        $data['paymentGatewayDiscountAmount'] = $totalData['paymentGatewayDiscountAmount'] ?? 0;
        $data['paymentGatewayDiscountMethod'] = $totalData['paymentGatewayDiscountMethod'] ?? null;
        $data['paymentGatewayDiscountLabel'] = $totalData['paymentGatewayDiscountLabel'] ?? null;
        $data['tax'] = $totalData['tax'];
        $data['cart_ids'] = $totalData['cart_ids'];
        $data['gift_ids'] = $totalData['gift_ids'];
        $data['product_ids'] = $totalData['product_ids'] ?? [];

        $subMethodService = app(PaymentSubMethodsService::class);
        $subResult = $subMethodService->apply($data['user_id'], $request, $data['final_before_sub']);

        if (isset($subResult['error'])) {
            return [
                'response' => $this->respondPaymentInputError($request, $subResult['error']),
            ];
        }

        return [
            'data' => $data,
            'totalData' => $totalData,
            'subResult' => $subResult,
            'remainingAmount' => $subResult['remaining_amount'],
        ];
    }

    protected function commitFinalizedPayment(int $userId, Request $request, array $paymentData, array $subResult, array $gatewayMeta = [], bool $subMethodsAlreadyCommitted = false): int
    {
        $finalizer = app(PaymentFinalizerService::class);
        $subMethodService = app(PaymentSubMethodsService::class);
        $attemptService = app(PaymentAttemptService::class);

        // Lock the PaymentAttempt to prevent concurrent finalization (race condition)
        $attemptId = $this->resolveAttemptId($request, $paymentData);
        if ($attemptId) {
            $lockedAttempt = $this->lockPaymentAttemptForFinalization($attemptId);
            if ($lockedAttempt === null) {
                // Attempt not found — proceed without lock (backward compat)
            } elseif ($lockedAttempt->status === PaymentAttempt::STATUS_PAID) {
                // Already finalized by another concurrent request
                \Log::info('Payment attempt already finalized, skipping duplicate finalization.', [
                    'attempt_id' => $attemptId,
                    'invoice_id' => $lockedAttempt->invoice_id,
                ]);
                return (int) ($lockedAttempt->invoice_id ?? 0);
            }
        }

        if (! $subMethodsAlreadyCommitted) {
            $refreshedSubResult = $subMethodService->apply($userId, $request, $paymentData['final_before_sub']);
            if (isset($refreshedSubResult['error'])) {
                throw new \RuntimeException((string) $refreshedSubResult['error']);
            }

            $subResult = $refreshedSubResult;
        }

        $subPayments = array_merge($subResult, [
            'gift_code' => $request->get('gift_code'),
            'gift_amount' => $request->get('gift_amount'),
            'coupon_discount_amount' => $paymentData['couponDiscountAmount'] ?? 0,
            'payment_gateway_discount_amount' => $paymentData['paymentGatewayDiscountAmount'] ?? 0,
            'payment_gateway_discount_method' => $paymentData['paymentGatewayDiscountMethod'] ?? null,
            'payment_gateway_discount_label' => $paymentData['paymentGatewayDiscountLabel'] ?? null,
        ]);

        $invoiceId = $finalizer->finalizePayment(
            $userId,
            $paymentData['final_before_sub'],
            $paymentData['tax'],
            $paymentData['discountAmount'],
            $paymentData['page'],
            $paymentData['cart_ids'] ?? [],
            $paymentData['gift_ids'] ?? [],
            $paymentData['payment_method'] ?? 'Sub Methods',
            $paymentData['couponCode'] ?? '',
            true,
            $subPayments,
            $gatewayMeta
        );

        // Only apply sub-methods if they haven't been committed by the caller already
        if (! $subMethodsAlreadyCommitted) {
            $subMethodService->apply($userId, $request, $paymentData['final_before_sub'], true);
        }

        $attemptService->markPaid($attemptId, [
            'invoice_id' => $invoiceId,
            'merchant_reference' => $gatewayMeta['merchant_reference'] ?? null,
            'gateway_transaction_id' => $gatewayMeta['transaction_id'] ?? null,
            'gateway_checkout_id' => $gatewayMeta['checkout_id'] ?? null,
            'gateway_order_id' => $gatewayMeta['order_id'] ?? null,
            'gateway_response' => $gatewayMeta['gateway_response'] ?? null,
            'callback_payload' => $gatewayMeta['callback_payload'] ?? null,
        ]);

        return $invoiceId;
    }

    protected function respondSubMethodOnlySuccess(Request $request, array $paymentData)
    {
        return $this->respondSuccess($request, 'Payment completed using sub methods.', [
            'paid' => true,
            'amount' => $paymentData['final_before_sub'],
            'payment_method' => $paymentData['payment_method'],
        ]);
    }

    protected function respondPaymentInputError(Request $request, string $message, int $status = 422)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()
            ->withInput()
            ->withErrors(['payment' => $message])
            ->with('error', $message);
    }

    protected function respondPayException(Request $request, \Throwable $e, int $status = 500)
    {
        report($e);
        $message = $this->presentableErrorMessage($e);

        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()
            ->withInput()
            ->withErrors(['payment' => $message])
            ->with('error', $message);
    }

    protected function isAlreadyFinalized(array $cartIds, array $giftIds): bool
    {
        if (empty($cartIds) && empty($giftIds)) {
            return false;
        }

        if (!empty($cartIds)) {
            $paid = Booking::whereIn('id', $cartIds)->paid()->count();
            if ($paid !== count($cartIds)) {
                return false;
            }
        }

        if (!empty($giftIds)) {
            $paidGifts = GiftCard::whereIn('id', $giftIds)->where('payment_status', 1)->count();
            if ($paidGifts !== count($giftIds)) {
                return false;
            }
        }

        return true;
    }

    protected function isClientDiscountMatching(?float $clientDiscount, float $expectedDiscount): bool
    {
        if ($clientDiscount === null) {
            return true;
        }

        return abs($clientDiscount - $expectedDiscount) <= 0.01;
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->is('api/*');
    }

    protected function respondSuccess(Request $request, string $message, array $data = [])
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data,
            ]);
        }

        return view('frontend.payment-status.captured');
    }

    protected function respondFailure(Request $request, string $message, int $status = 400)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return view('frontend.payment-status.failed', ['message' => $message]);
    }

    protected function presentableErrorMessage(\Throwable $e): string
    {
        return trim($e->getMessage()) !== '' ? $e->getMessage() : __('messages.payment_failed');
    }

    protected function createPaymentAttempt(Request $request, array $paymentData, string $gateway, float $amount): array
    {
        $attempt = app(PaymentAttemptService::class)->create($paymentData, $amount, $gateway, $request);
        $paymentData['attempt_id'] = $attempt->id;

        return [$attempt, $paymentData];
    }

    protected function resolveAttemptId(Request $request, ?array $paymentData = null): ?int
    {
        $attemptId = $request->input('attempt_id', $paymentData['attempt_id'] ?? null);

        return is_numeric($attemptId) ? (int) $attemptId : null;
    }

    protected function markPaymentAttemptPending(?int $attemptId, array $attributes = []): ?PaymentAttempt
    {
        return app(PaymentAttemptService::class)->markPending($attemptId, $attributes);
    }

    protected function markPaymentAttemptFailed(?int $attemptId, ?string $message = null, array $attributes = []): ?PaymentAttempt
    {
        return app(PaymentAttemptService::class)->markFailed($attemptId, $message, $attributes);
    }

    protected function markPaymentAttemptCancelled(?int $attemptId, ?string $message = null, array $attributes = []): ?PaymentAttempt
    {
        return app(PaymentAttemptService::class)->markCancelled($attemptId, $message, $attributes);
    }

    protected function markPaymentAttemptPaid(?int $attemptId, array $attributes = []): ?PaymentAttempt
    {
        return app(PaymentAttemptService::class)->markPaid($attemptId, $attributes);
    }

    /**
     * Lock the PaymentAttempt row to prevent concurrent finalization (race condition).
     * Returns null if the attempt doesn't exist.
     */
    protected function lockPaymentAttemptForFinalization(?int $attemptId): ?PaymentAttempt
    {
        if (! $attemptId) {
            return null;
        }

        return PaymentAttempt::where('id', $attemptId)->lockForUpdate()->first();
    }
}
