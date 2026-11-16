<?php

namespace Modules\Wallet\Services;

use App\Models\User;
use App\Services\HyperpayService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHistory;
use Modules\Wallet\Models\WalletTopupAttempt;

/**
 * Wallet top-up for the mobile app.
 *
 * The app never tells the server that a payment succeeded. The server creates the
 * HyperPay checkout, and only credits the wallet after asking HyperPay directly
 * (server-to-server) about *its own* checkout id. Each attempt and each gateway
 * payment id can credit the wallet at most once.
 */
class WalletTopupService
{
    public const BRANDS = ['VISA', 'MADA', 'APPLEPAY'];

    /** Seconds between two gateway lookups for the same pending attempt. */
    private const RECHECK_COOLDOWN_SECONDS = 5;

    /** Extra minutes after expires_at before an unpaid attempt is declared expired. */
    private const EXPIRY_GRACE_MINUTES = 5;

    public function __construct(private HyperpayService $hyperpay)
    {
    }

    public static function normalizeBrand(mixed $brand): string
    {
        return match (strtoupper(trim((string) $brand))) {
            'MADA' => 'MADA',
            'APPLEPAY', 'APPLE_PAY', 'APPLE' => 'APPLEPAY',
            default => 'VISA',
        };
    }

    /**
     * Classify a HyperPay result code.
     *
     * @return string one of: paid | pending | cancelled | failed
     */
    public static function classifyResultCode(?string $code, bool $allowTestMode): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return WalletTopupAttempt::STATUS_PENDING;
        }

        // Test-mode "success" never captured money; only accept it on the test gateway.
        if (in_array($code, ['000.100.110', '000.100.111', '000.100.112'], true)) {
            return $allowTestMode ? WalletTopupAttempt::STATUS_PAID : WalletTopupAttempt::STATUS_FAILED;
        }

        if (preg_match('/^(000\.000\.|000\.100\.1|000\.[36]|000\.400\.[1][12]0)/', $code) === 1) {
            return WalletTopupAttempt::STATUS_PAID;
        }

        if (preg_match('/^(000\.200|800\.400\.5|100\.400\.500)/', $code) === 1) {
            return WalletTopupAttempt::STATUS_PENDING;
        }

        if ($code === '100.396.101') {
            return WalletTopupAttempt::STATUS_CANCELLED;
        }

        return WalletTopupAttempt::STATUS_FAILED;
    }

    public static function isTestGateway(): bool
    {
        $baseUrl = strtolower((string) config('services.hyperpay.base_url', ''));

        return str_contains($baseUrl, 'test.oppwa.com');
    }

    /**
     * Create an attempt and its HyperPay checkout.
     *
     * @throws \RuntimeException when the gateway refuses to create the checkout.
     */
    public function initiate(User $user, float $amount, string $brand): WalletTopupAttempt
    {
        $brand = self::normalizeBrand($brand);
        $amount = round($amount, 2);
        $ttl = max(5, (int) config('services.wallet_topup.ttl_minutes', 30));

        $attempt = WalletTopupAttempt::create([
            'attempt_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'SAR',
            'gateway' => 'hyperpay',
            'brand' => $brand,
            'status' => WalletTopupAttempt::STATUS_PENDING,
            // HyperPay limits merchantTransactionId to 16 characters.
            'merchant_transaction_id' => 'A' . Str::upper(Str::random(15)),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        try {
            $checkout = $this->hyperpay->createCheckout(
                $amount,
                $attempt->merchant_transaction_id,
                route('wallet.app-topup.return', ['attempt' => $attempt->attempt_id]),
                $this->customerData($user),
                $brand
            );
        } catch (\Throwable $e) {
            $attempt->update([
                'status' => WalletTopupAttempt::STATUS_FAILED,
                'result_description' => Str::limit($e->getMessage(), 250),
            ]);

            Log::error('Wallet app top-up: checkout creation failed.', [
                'attempt_id' => $attempt->attempt_id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('gateway_unavailable', 0, $e);
        }

        $checkoutId = (string) ($checkout['id'] ?? '');
        if ($checkoutId === '') {
            $attempt->update([
                'status' => WalletTopupAttempt::STATUS_FAILED,
                'result_code' => (string) data_get($checkout, 'result.code', ''),
                'result_description' => Str::limit((string) data_get($checkout, 'result.description', 'No checkout id returned.'), 250),
            ]);

            throw new \RuntimeException('gateway_unavailable');
        }

        $attempt->update(['checkout_id' => $checkoutId]);

        return $attempt->refresh();
    }

    public function paymentUrl(WalletTopupAttempt $attempt): string
    {
        return URL::temporarySignedRoute(
            'wallet.app-topup.pay',
            $attempt->expires_at ?? now()->addMinutes(30),
            ['attempt' => $attempt->attempt_id]
        );
    }

    public function widgetScriptUrl(WalletTopupAttempt $attempt): string
    {
        return $this->hyperpay->widgetScriptUrl((string) $attempt->checkout_id, $attempt->brand);
    }

    /**
     * Ask HyperPay about this attempt's checkout and settle it if possible.
     * Safe to call any number of times, from the return URL or the status endpoint.
     */
    public function refresh(WalletTopupAttempt $attempt, bool $force = false): WalletTopupAttempt
    {
        if ($attempt->isFinal() || ! $attempt->checkout_id) {
            return $attempt;
        }

        if (! $force && $attempt->last_checked_at && $attempt->last_checked_at->diffInSeconds(now()) < self::RECHECK_COOLDOWN_SECONDS) {
            return $attempt;
        }

        $attempt->forceFill(['last_checked_at' => now()])->save();

        try {
            $payment = $this->fetchPayment($attempt);
        } catch (\Throwable $e) {
            // HyperPay answers with an error while no payment exists yet, and after the
            // checkout session is gone. Neither means money was taken.
            Log::info('Wallet app top-up: gateway status lookup returned no payment.', [
                'attempt_id' => $attempt->attempt_id,
                'message' => $e->getMessage(),
            ]);

            return $this->expireIfOverdue($attempt);
        }

        $resultCode = (string) data_get($payment, 'result.code', '');
        $resultDescription = Str::limit((string) data_get($payment, 'result.description', ''), 250);
        $status = self::classifyResultCode($resultCode, self::isTestGateway());

        if ($status === WalletTopupAttempt::STATUS_PENDING) {
            $attempt->update(['result_code' => $resultCode ?: null, 'result_description' => $resultDescription ?: null]);

            return $this->expireIfOverdue($attempt);
        }

        if ($status !== WalletTopupAttempt::STATUS_PAID) {
            return $this->settleWithoutCredit($attempt, $status, $resultCode, $resultDescription, $payment);
        }

        $mismatch = $this->paymentMismatch($attempt, $payment);
        if ($mismatch !== null) {
            Log::critical('Wallet app top-up: paid gateway response does not match attempt.', [
                'attempt_id' => $attempt->attempt_id,
                'reason' => $mismatch,
                'gateway_transaction_id' => data_get($payment, 'id'),
            ]);

            return $this->settleWithoutCredit($attempt, WalletTopupAttempt::STATUS_FAILED, $resultCode, $mismatch, $payment);
        }

        return $this->credit($attempt, $payment, $resultCode, $resultDescription);
    }

    /** Public representation returned to the app. */
    public function present(WalletTopupAttempt $attempt): array
    {
        $data = [
            'attempt_id' => $attempt->attempt_id,
            'status' => $attempt->status,
            'amount' => number_format((float) $attempt->amount, 2, '.', ''),
            'currency' => $attempt->currency,
            'brand' => $attempt->brand,
            'checkout_id' => $attempt->checkout_id,
            'wallet_credited' => $attempt->status === WalletTopupAttempt::STATUS_PAID,
            'result' => [
                'code' => $attempt->result_code,
                'description' => $attempt->result_description,
            ],
            'created_at' => optional($attempt->created_at)->toIso8601String(),
            'expires_at' => optional($attempt->expires_at)->toIso8601String(),
            'paid_at' => optional($attempt->paid_at)->toIso8601String(),
        ];

        if ($attempt->status === WalletTopupAttempt::STATUS_PAID) {
            $wallet = Wallet::where('user_id', $attempt->user_id)->first();
            $data['available_balance'] = number_format((float) ($wallet->amount ?? 0), 2, '.', '');
        }

        return $data;
    }

    private function fetchPayment(WalletTopupAttempt $attempt): array
    {
        // Always our own checkout: the client cannot point us at another payment.
        $resourcePath = '/v1/checkouts/' . rawurlencode((string) $attempt->checkout_id) . '/payment';

        try {
            return $this->hyperpay->fetchPaymentStatus($resourcePath, $attempt->brand);
        } catch (\RuntimeException $e) {
            $message = strtolower($e->getMessage());
            if (! str_contains($message, 'invalid or missing parameter') && ! str_contains($message, 'verification request')) {
                throw $e;
            }

            $alternate = $attempt->brand === 'MADA' ? 'VISA' : 'MADA';

            return $this->hyperpay->fetchPaymentStatus($resourcePath, $alternate);
        }
    }

    private function paymentMismatch(WalletTopupAttempt $attempt, array $payment): ?string
    {
        $mtid = (string) data_get($payment, 'merchantTransactionId', '');
        if ($mtid !== '' && $mtid !== $attempt->merchant_transaction_id) {
            return 'merchantTransactionId mismatch';
        }

        $paidAmount = data_get($payment, 'amount');
        if ($paidAmount === null || $paidAmount === '' || abs((float) $paidAmount - (float) $attempt->amount) > 0.009) {
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

    private function credit(WalletTopupAttempt $attempt, array $payment, string $resultCode, string $resultDescription): WalletTopupAttempt
    {
        $gatewayTransactionId = (string) data_get($payment, 'id');
        $credited = false;

        $settled = DB::transaction(function () use ($attempt, $payment, $gatewayTransactionId, $resultCode, $resultDescription, &$credited) {
            /** @var WalletTopupAttempt $locked */
            $locked = WalletTopupAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletTopupAttempt::STATUS_PENDING) {
                return $locked;
            }

            $alreadyUsed = WalletTopupAttempt::where('gateway_transaction_id', $gatewayTransactionId)
                ->where('id', '!=', $locked->id)
                ->exists()
                || WalletHistory::where('activity_type', 'deposit')
                    ->where('activity_data', 'like', '%' . json_encode($gatewayTransactionId) . '%')
                    ->exists();

            if ($alreadyUsed) {
                $locked->update([
                    'status' => WalletTopupAttempt::STATUS_FAILED,
                    'result_code' => $resultCode,
                    'result_description' => 'Duplicate gateway transaction.',
                    'gateway_response' => $payment,
                ]);

                return $locked;
            }

            $user = User::find($locked->user_id);
            $wallet = Wallet::where('user_id', $locked->user_id)->lockForUpdate()->first()
                ?? Wallet::create([
                    'user_id' => $locked->user_id,
                    'amount' => 0,
                    'title' => trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                    'status' => 1,
                ]);

            $wallet->increment('amount', (float) $locked->amount);

            $history = WalletHistory::create([
                'datetime' => now(),
                'user_id' => $locked->user_id,
                'activity_type' => 'deposit',
                'activity_message' => 'Wallet balance added',
                'activity_data' => json_encode([
                    'gateway' => 'hyperpay',
                    'source' => 'app',
                    'attempt_id' => $locked->attempt_id,
                    'hyperpay_transaction_id' => $gatewayTransactionId,
                    'merchant_transaction_id' => $locked->merchant_transaction_id,
                    'checkout_id' => $locked->checkout_id,
                    'credit_debit_amount' => (float) $locked->amount,
                    'transaction_type' => 'credit',
                    'status' => $resultDescription ?: $resultCode,
                ]),
            ]);

            $locked->update([
                'status' => WalletTopupAttempt::STATUS_PAID,
                'gateway_transaction_id' => $gatewayTransactionId,
                'result_code' => $resultCode,
                'result_description' => $resultDescription,
                'wallet_history_id' => $history->id,
                'gateway_response' => $payment,
                'paid_at' => now(),
            ]);

            $credited = true;

            return $locked;
        });

        if ($credited) {
            Log::info('Wallet app top-up credited.', [
                'attempt_id' => $settled->attempt_id,
                'user_id' => $settled->user_id,
                'amount' => (float) $settled->amount,
                'gateway_transaction_id' => $gatewayTransactionId,
            ]);

            try {
                $user = User::find($settled->user_id);
                if ($user) {
                    app(UserNotificationService::class)->notifyWalletTopUp($user, (float) $settled->amount);
                }
            } catch (\Throwable $e) {
                Log::warning('Wallet app top-up: notification failed.', ['attempt_id' => $settled->attempt_id, 'error' => $e->getMessage()]);
            }
        }

        return $settled;
    }

    private function settleWithoutCredit(WalletTopupAttempt $attempt, string $status, string $resultCode, string $description, array $payment): WalletTopupAttempt
    {
        return DB::transaction(function () use ($attempt, $status, $resultCode, $description, $payment) {
            $locked = WalletTopupAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === WalletTopupAttempt::STATUS_PENDING) {
                $locked->update([
                    'status' => $status,
                    'result_code' => $resultCode ?: null,
                    'result_description' => $description ?: null,
                    'gateway_response' => $payment,
                ]);
            }

            return $locked;
        });
    }

    private function expireIfOverdue(WalletTopupAttempt $attempt): WalletTopupAttempt
    {
        $deadline = ($attempt->expires_at ?? $attempt->created_at->copy()->addMinutes(30))
            ->copy()->addMinutes(self::EXPIRY_GRACE_MINUTES);

        if (now()->lessThan($deadline)) {
            return $attempt;
        }

        return $this->settleWithoutCredit(
            $attempt,
            WalletTopupAttempt::STATUS_EXPIRED,
            (string) $attempt->result_code,
            'Payment session expired without a completed payment.',
            []
        );
    }

    private function customerData(User $user): array
    {
        $givenName = trim((string) ($user->first_name ?? ''));
        $surname = trim((string) ($user->last_name ?? ''));
        $country = strtoupper(trim((string) ($user->country ?? 'SA')));

        return [
            'given_name' => $givenName !== '' ? $givenName : 'Customer',
            'surname' => $surname !== '' ? $surname : ($givenName !== '' ? $givenName : 'Customer'),
            'email' => $user->email,
            'mobile' => $user->mobile,
            'billing_street' => trim((string) ($user->address ?? '')) ?: 'Main Street 3252',
            'billing_city' => trim((string) ($user->city ?? '')) ?: 'Riyadh',
            'billing_state' => trim((string) ($user->city ?? '')) ?: 'Riyadh',
            'billing_country' => strlen($country) === 2 ? $country : 'SA',
            'billing_postcode' => '25262',
        ];
    }
}
