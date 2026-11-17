<?php

namespace App\Services\PaymentRequest;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttempt;
use App\Models\User;
use App\Notifications\PaymentRequestPaid;
use App\Services\HyperpayService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Wallet\Services\WalletTopupService;

/**
 * Mini invoices / payment links.
 *
 * Rules (same as the app wallet top-up):
 *  - The amount always comes from the stored payment request, never from the browser.
 *  - A request is marked paid only after the server asks HyperPay about *its own*
 *    checkout id. Return-URL query parameters are ignored.
 *  - Each attempt and each gateway payment id can settle a request at most once.
 *  - This flow never touches the store's payment_attempts / invoices / finalizer.
 */
class PaymentRequestService
{
    public const MIN_AMOUNT = 1;

    public const MAX_AMOUNT = 999999.99;

    /** Payment methods offered on the public page (same as booking payment links). */
    public const BRANDS = ['VISA', 'MADA'];

    public const SUPERSEDED_NOTE = 'Superseded by a newer checkout.';

    public const CANCELLED_BY_ADMIN_NOTE = 'Payment request cancelled by admin.';

    public const DUPLICATE_PAYMENT_NOTE = 'Duplicate payment for an already paid request. Refund required.';

    /** Cancelled attempts with these notes are re-checked until their session expires, so a late payment is never lost. */
    private const RECHECKABLE_NOTES = [self::SUPERSEDED_NOTE, self::CANCELLED_BY_ADMIN_NOTE];

    private const RECHECK_COOLDOWN_SECONDS = 5;

    private const EXPIRY_GRACE_MINUTES = 5;

    /** An open checkout is reused only while it still has this many minutes left. */
    private const REUSE_MIN_REMAINING_MINUTES = 5;

    private const CHECKOUT_TTL_MINUTES = 30;

    // ---------------------------------------------------------------------
    // Admin side
    // ---------------------------------------------------------------------

    /**
     * @param  array{customer_name: string, customer_mobile: ?string, description: string, amount: string}  $data
     */
    public function create(array $data, ?int $createdBy): PaymentRequest
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $request = PaymentRequest::create([
                'token' => Str::random(48),
                'customer_name' => $data['customer_name'],
                'customer_mobile' => $data['customer_mobile'] ?? null,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'currency' => 'SAR',
                'status' => PaymentRequest::STATUS_PENDING,
                'created_by' => $createdBy,
            ]);

            // Derived from the auto-increment id: unique without a race.
            $request->number = sprintf('PR-%06d', $request->id);
            $request->save();

            return $request;
        });
    }

    /**
     * Cancel a pending request. The gateway is asked first: a request that was in fact
     * paid cannot be cancelled. Open checkouts stay re-checkable until they expire.
     *
     * @throws \DomainException when the request is already paid.
     */
    public function cancel(PaymentRequest $request, ?int $userId): PaymentRequest
    {
        $request = $this->refreshOpenAttempts($request, force: true);

        if ($request->status === PaymentRequest::STATUS_PAID) {
            throw new \DomainException(__('payment_requests.errors.already_paid'));
        }

        return DB::transaction(function () use ($request, $userId) {
            $locked = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentRequest::STATUS_PENDING) {
                return $locked;
            }

            $locked->update([
                'status' => PaymentRequest::STATUS_CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);

            $locked->attempts()
                ->where('status', PaymentRequestAttempt::STATUS_PENDING)
                ->update([
                    'status' => PaymentRequestAttempt::STATUS_CANCELLED,
                    'result_description' => self::CANCELLED_BY_ADMIN_NOTE,
                ]);

            return $locked;
        });
    }

    // ---------------------------------------------------------------------
    // Customer side
    // ---------------------------------------------------------------------

    public static function normalizeBrand(mixed $brand): ?string
    {
        $brand = strtoupper(trim((string) $brand));

        return in_array($brand, self::BRANDS, true) ? $brand : null;
    }

    /**
     * Open (or reuse) a HyperPay checkout for the exact stored amount.
     *
     * @throws \DomainException request not payable / invalid method
     * @throws \RuntimeException gateway refused to create the checkout
     */
    public function startCheckout(PaymentRequest $request, string $brand): PaymentRequestAttempt
    {
        $brand = self::normalizeBrand($brand);
        if ($brand === null) {
            throw new \DomainException(__('payment_requests.errors.invalid_method'));
        }

        // Settle anything the gateway already knows before opening a new charge.
        $request = $this->refreshOpenAttempts($request, force: true);

        // The row lock serializes double clicks / parallel tabs: the second request
        // waits, then reuses the checkout the first one opened.
        [$attempt, $error] = DB::transaction(function () use ($request, $brand) {
            $locked = PaymentRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentRequest::STATUS_PENDING) {
                throw new \DomainException(__('payment_requests.errors.not_payable'));
            }

            $open = $locked->attempts()
                ->where('status', PaymentRequestAttempt::STATUS_PENDING)
                ->whereNotNull('checkout_id')
                ->lockForUpdate()
                ->get();

            $reusable = $open->first(fn (PaymentRequestAttempt $a) => $a->brand === $brand
                && $a->expires_at !== null
                && $a->expires_at->gt(now()->addMinutes(self::REUSE_MIN_REMAINING_MINUTES))
                && $this->sameAmount($a->amount, $locked->amount));

            if ($reusable) {
                return [$reusable, null];
            }

            foreach ($open as $old) {
                $old->update([
                    'status' => PaymentRequestAttempt::STATUS_CANCELLED,
                    'result_description' => self::SUPERSEDED_NOTE,
                ]);
            }

            $attempt = PaymentRequestAttempt::create([
                'uuid' => (string) Str::uuid(),
                'payment_request_id' => $locked->id,
                'amount' => $locked->amount,
                'currency' => 'SAR',
                'brand' => $brand,
                'status' => PaymentRequestAttempt::STATUS_PENDING,
                // HyperPay limits merchantTransactionId to 16 characters.
                'merchant_transaction_id' => 'P'.Str::upper(Str::random(15)),
                'expires_at' => now()->addMinutes(self::CHECKOUT_TTL_MINUTES),
            ]);

            try {
                $checkout = $this->hyperpay()->createCheckout(
                    (float) $locked->amount,
                    $attempt->merchant_transaction_id,
                    route('payment-request.return', ['attempt' => $attempt->uuid]),
                    $this->customerData($locked),
                    $brand
                );
            } catch (\Throwable $e) {
                $attempt->update([
                    'status' => PaymentRequestAttempt::STATUS_FAILED,
                    'result_description' => Str::limit($e->getMessage(), 250),
                ]);

                return [$attempt, $e];
            }

            $checkoutId = (string) ($checkout['id'] ?? '');
            if ($checkoutId === '') {
                $attempt->update([
                    'status' => PaymentRequestAttempt::STATUS_FAILED,
                    'result_code' => Str::limit((string) data_get($checkout, 'result.code', ''), 30, ''),
                    'result_description' => Str::limit((string) data_get($checkout, 'result.description', 'No checkout id returned.'), 250),
                ]);

                return [$attempt, new \RuntimeException('No checkout id returned.')];
            }

            $attempt->update(['checkout_id' => $checkoutId]);

            return [$attempt->refresh(), null];
        });

        if ($error) {
            Log::error('Payment request: checkout creation failed.', [
                'payment_request_id' => $request->id,
                'attempt' => $attempt->uuid,
                'error' => $error->getMessage(),
            ]);

            throw new \RuntimeException('gateway_unavailable', 0, $error);
        }

        return $attempt;
    }

    public function widgetScriptUrl(PaymentRequestAttempt $attempt): string
    {
        return $this->hyperpay()->widgetScriptUrl((string) $attempt->checkout_id, $attempt->brand);
    }

    // ---------------------------------------------------------------------
    // Verification
    // ---------------------------------------------------------------------

    /** Re-check every attempt of a request that could still turn out paid. */
    public function refreshOpenAttempts(PaymentRequest $request, bool $force = false): PaymentRequest
    {
        $attempts = $request->attempts()
            ->whereNotNull('checkout_id')
            ->whereIn('status', [PaymentRequestAttempt::STATUS_PENDING, PaymentRequestAttempt::STATUS_CANCELLED])
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentRequestAttempt $a) => $this->isRecheckable($a));

        foreach ($attempts as $attempt) {
            try {
                $this->refresh($attempt, $force);
            } catch (\Throwable $e) {
                Log::warning('Payment request: attempt refresh failed.', ['attempt' => $attempt->uuid, 'error' => $e->getMessage()]);
            }
        }

        return $request->fresh();
    }

    /** Background sweep (payment-requests:reconcile). Returns how many attempts were checked. */
    public function reconcileOpenAttempts(): int
    {
        $attempts = PaymentRequestAttempt::query()
            ->whereNotNull('checkout_id')
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('status', [PaymentRequestAttempt::STATUS_PENDING, PaymentRequestAttempt::STATUS_CANCELLED])
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentRequestAttempt $a) => $this->isRecheckable($a));

        foreach ($attempts as $attempt) {
            try {
                $this->refresh($attempt);
            } catch (\Throwable $e) {
                Log::warning('Payment request reconcile failed for attempt.', ['attempt' => $attempt->uuid, 'error' => $e->getMessage()]);
            }
        }

        return $attempts->count();
    }

    /**
     * Ask HyperPay about this attempt's own checkout and settle it if possible.
     * Safe to call any number of times (return URL, page reload, cron).
     */
    public function refresh(PaymentRequestAttempt $attempt, bool $force = false): PaymentRequestAttempt
    {
        if (! $attempt->checkout_id || ! $this->isRecheckable($attempt)) {
            return $attempt;
        }

        if (! $force && $attempt->last_checked_at && $attempt->last_checked_at->diffInSeconds(now()) < self::RECHECK_COOLDOWN_SECONDS) {
            return $attempt;
        }

        $attempt->forceFill(['last_checked_at' => now()])->save();

        try {
            $payment = $this->fetchPayment($attempt);
        } catch (\Throwable $e) {
            // HyperPay errors while no payment exists yet and after the session is gone.
            Log::info('Payment request: gateway lookup returned no payment.', ['attempt' => $attempt->uuid, 'message' => $e->getMessage()]);

            return $this->expireIfOverdue($attempt);
        }

        $resultCode = (string) data_get($payment, 'result.code', '');
        $resultDescription = Str::limit((string) data_get($payment, 'result.description', ''), 250);
        $status = WalletTopupService::classifyResultCode($resultCode, WalletTopupService::isTestGateway());

        if ($status === PaymentRequestAttempt::STATUS_PENDING) {
            if ($attempt->status === PaymentRequestAttempt::STATUS_PENDING) {
                $attempt->update(['result_code' => $resultCode ?: null, 'result_description' => $resultDescription ?: null]);
            }

            return $this->expireIfOverdue($attempt);
        }

        if ($status !== PaymentRequestAttempt::STATUS_PAID) {
            return $this->settleWithoutPayment($attempt, $status, $resultCode, $resultDescription, $payment);
        }

        $mismatch = $this->paymentMismatch($attempt, $payment);
        if ($mismatch !== null) {
            Log::critical('Payment request: paid gateway response does not match attempt.', [
                'attempt' => $attempt->uuid,
                'reason' => $mismatch,
                'gateway_transaction_id' => data_get($payment, 'id'),
            ]);

            return $this->settleWithoutPayment($attempt, PaymentRequestAttempt::STATUS_FAILED, $resultCode, $mismatch, $payment);
        }

        return $this->markPaid($attempt, $payment, $resultCode, $resultDescription);
    }

    public function isRecheckable(PaymentRequestAttempt $attempt): bool
    {
        if ($attempt->status === PaymentRequestAttempt::STATUS_PENDING) {
            return true;
        }

        return $attempt->status === PaymentRequestAttempt::STATUS_CANCELLED
            && in_array($attempt->result_description, self::RECHECKABLE_NOTES, true)
            && $attempt->expires_at !== null
            && now()->lessThan($attempt->expires_at->copy()->addMinutes(self::EXPIRY_GRACE_MINUTES));
    }

    /** Returns a reason string when the gateway's paid response does not match what we asked for. */
    public function paymentMismatch(PaymentRequestAttempt $attempt, array $payment): ?string
    {
        $mtid = (string) data_get($payment, 'merchantTransactionId', '');
        if ($mtid !== '' && $mtid !== $attempt->merchant_transaction_id) {
            return 'merchantTransactionId mismatch';
        }

        $paidAmount = data_get($payment, 'amount');
        if ($paidAmount === null || $paidAmount === '' || ! $this->sameAmount($paidAmount, $attempt->amount)) {
            return 'Paid amount does not match the requested amount.';
        }

        $currency = strtoupper((string) data_get($payment, 'currency', ''));
        if ($currency !== '' && $currency !== 'SAR') {
            return 'Currency mismatch.';
        }

        if ((string) data_get($payment, 'id', '') === '') {
            return 'Gateway payment id missing.';
        }

        return null;
    }

    /** Keep only non-sensitive gateway fields (no card, customer or billing data). */
    public static function sanitizeGatewayResponse(array $payment): array
    {
        $keep = ['id', 'paymentType', 'paymentBrand', 'amount', 'currency', 'merchantTransactionId', 'timestamp', 'ndc'];
        $clean = array_intersect_key($payment, array_flip($keep));

        if (isset($payment['result']) && is_array($payment['result'])) {
            $clean['result'] = array_intersect_key($payment['result'], array_flip(['code', 'description']));
        }

        return $clean;
    }

    private function markPaid(PaymentRequestAttempt $attempt, array $payment, string $resultCode, string $resultDescription): PaymentRequestAttempt
    {
        $gatewayTransactionId = (string) data_get($payment, 'id');
        $event = null;

        $settled = DB::transaction(function () use ($attempt, $payment, $gatewayTransactionId, $resultCode, $resultDescription, &$event) {
            // Lock order: request, then attempt (same everywhere) to avoid deadlocks.
            $request = PaymentRequest::whereKey($attempt->payment_request_id)->lockForUpdate()->firstOrFail();
            $locked = PaymentRequestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            // Already settled by a concurrent callback / reload: nothing to do.
            if (! $this->isRecheckable($locked)) {
                return $locked;
            }

            $usedElsewhere = PaymentRequestAttempt::where('gateway_transaction_id', $gatewayTransactionId)
                ->where('id', '!=', $locked->id)
                ->exists();

            if ($usedElsewhere) {
                $locked->update([
                    'status' => PaymentRequestAttempt::STATUS_FAILED,
                    'result_code' => $resultCode,
                    'result_description' => 'Duplicate gateway transaction.',
                    'gateway_response' => self::sanitizeGatewayResponse($payment),
                ]);

                return $locked;
            }

            // Money was taken on this attempt, so it is always recorded as paid.
            $alreadyPaid = $request->status === PaymentRequest::STATUS_PAID;

            $locked->update([
                'status' => PaymentRequestAttempt::STATUS_PAID,
                'gateway_transaction_id' => $gatewayTransactionId,
                'result_code' => $resultCode,
                'result_description' => $alreadyPaid ? self::DUPLICATE_PAYMENT_NOTE : ($resultDescription ?: null),
                'gateway_response' => self::sanitizeGatewayResponse($payment),
                'paid_at' => now(),
            ]);

            if ($alreadyPaid) {
                $event = PaymentRequestPaid::EVENT_DUPLICATE;

                return $locked;
            }

            $event = $request->status === PaymentRequest::STATUS_CANCELLED
                ? PaymentRequestPaid::EVENT_PAID_AFTER_CANCEL
                : PaymentRequestPaid::EVENT_PAID;

            $request->update([
                'status' => PaymentRequest::STATUS_PAID,
                'paid_at' => now(),
                'paid_attempt_id' => $locked->id,
                'gateway_transaction_id' => $gatewayTransactionId,
            ]);

            return $locked;
        });

        if ($event !== null) {
            $request = PaymentRequest::find($settled->payment_request_id);

            $log = $event === PaymentRequestPaid::EVENT_PAID ? 'info' : 'critical';
            Log::$log('Payment request settled.', [
                'payment_request' => $request?->number,
                'event' => $event,
                'amount' => (string) $settled->amount,
                'gateway_transaction_id' => $gatewayTransactionId,
            ]);

            if ($request) {
                $this->notifyAdmins($request, $event);
            }
        }

        return $settled;
    }

    private function settleWithoutPayment(PaymentRequestAttempt $attempt, string $status, string $resultCode, string $description, array $payment): PaymentRequestAttempt
    {
        return DB::transaction(function () use ($attempt, $status, $resultCode, $description, $payment) {
            $locked = PaymentRequestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentRequestAttempt::STATUS_PENDING) {
                $locked->update([
                    'status' => $status,
                    'result_code' => $resultCode ?: null,
                    'result_description' => $description ?: null,
                    'gateway_response' => $payment ? self::sanitizeGatewayResponse($payment) : null,
                ]);
            }

            return $locked;
        });
    }

    private function expireIfOverdue(PaymentRequestAttempt $attempt): PaymentRequestAttempt
    {
        $deadline = ($attempt->expires_at ?? $attempt->created_at->copy()->addMinutes(self::CHECKOUT_TTL_MINUTES))
            ->copy()->addMinutes(self::EXPIRY_GRACE_MINUTES);

        if (now()->lessThan($deadline)) {
            return $attempt;
        }

        if ($attempt->status === PaymentRequestAttempt::STATUS_CANCELLED) {
            // Re-check window is over; nothing was paid. Keep it cancelled.
            return $attempt;
        }

        return $this->settleWithoutPayment(
            $attempt,
            PaymentRequestAttempt::STATUS_EXPIRED,
            (string) $attempt->result_code,
            'Payment session expired without a completed payment.',
            []
        );
    }

    private function fetchPayment(PaymentRequestAttempt $attempt): array
    {
        // Always our own checkout: the browser cannot point us at another payment.
        $resourcePath = '/v1/checkouts/'.rawurlencode((string) $attempt->checkout_id).'/payment';

        try {
            return $this->hyperpay()->fetchPaymentStatus($resourcePath, $attempt->brand);
        } catch (\RuntimeException $e) {
            $message = strtolower($e->getMessage());
            if (! str_contains($message, 'invalid or missing parameter') && ! str_contains($message, 'verification request')) {
                throw $e;
            }

            return $this->hyperpay()->fetchPaymentStatus($resourcePath, $attempt->brand === 'MADA' ? 'VISA' : 'MADA');
        }
    }

    // ---------------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------------

    private function notifyAdmins(PaymentRequest $request, string $event): void
    {
        try {
            $recipients = $this->adminRecipients($request);
        } catch (\Throwable $e) {
            Log::warning('Payment request: could not resolve admin recipients.', ['error' => $e->getMessage()]);

            return;
        }

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::sendNow($recipients, new PaymentRequestPaid($request, $event, ['database']));
        } catch (\Throwable $e) {
            Log::warning('Payment request: dashboard notification failed.', ['payment_request' => $request->number, 'error' => $e->getMessage()]);
        }

        if (! self::mailConfigured()) {
            return;
        }

        $mailRecipients = $recipients->filter(fn (User $u) => filter_var((string) $u->email, FILTER_VALIDATE_EMAIL));
        if ($mailRecipients->isEmpty()) {
            return;
        }

        try {
            Notification::sendNow($mailRecipients, new PaymentRequestPaid($request, $event, ['mail']));
        } catch (\Throwable $e) {
            Log::warning('Payment request: email notification failed.', ['payment_request' => $request->number, 'error' => $e->getMessage()]);
        }
    }

    /** Admin-role users plus the dashboard user who created the request. */
    private function adminRecipients(PaymentRequest $request): Collection
    {
        $recipients = collect();

        $roleModel = config('permission.models.role');
        if ($roleModel && $roleModel::where('name', 'admin')->exists()) {
            $recipients = User::role('admin')->get();
        }

        if ($request->created_by && ! $recipients->contains('id', $request->created_by)) {
            $creator = User::find($request->created_by);
            if ($creator) {
                $recipients->push($creator);
            }
        }

        return $recipients->unique('id')->values();
    }

    /** Email is sent only when the site already has a real mailer configured. */
    public static function mailConfigured(): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['', 'log', 'array'], true)) {
            return false;
        }

        return filled(config('mail.from.address'));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function hyperpay(): HyperpayService
    {
        return app(HyperpayService::class);
    }

    private function sameAmount(mixed $a, mixed $b): bool
    {
        return number_format((float) $a, 2, '.', '') === number_format((float) $b, 2, '.', '');
    }

    private function customerData(PaymentRequest $request): array
    {
        // HyperpayService strips non-Latin names and falls back to "Customer".
        $parts = preg_split('/\s+/', trim($request->customer_name), 2);

        return [
            'given_name' => $parts[0] ?? '',
            'surname' => $parts[1] ?? '',
            'mobile' => ltrim((string) $request->customer_mobile, '+'),
            'country' => 'SA',
        ];
    }
}
