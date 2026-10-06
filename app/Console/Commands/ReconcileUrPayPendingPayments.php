<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Services\Payment\PaymentAttemptService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReconcileUrPayPendingPayments extends Command
{
    protected $signature = 'urpay:reconcile-pending
                            {--minutes=30 : عدد الدقائق اللي المعاملة تكون معلقة قبل ما نتحقق منها}
                            {--dry-run : يطبع النتائج بس من غير ما يعمل أي تغيير}';

    protected $description = 'التحقق من معاملات UrPay المعلقة عن طريق استعلام البنك مباشرة وتسوية حالتها';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');

        $pendingAttempts = PaymentAttempt::where('gateway', 'urpay')
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_INITIATED])
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('id')
            ->get();

        if ($pendingAttempts->isEmpty()) {
            $this->info('لا توجد معاملات معلقة تحتاج تسوية.');
            return self::SUCCESS;
        }

        $this->info("وُجدت {$pendingAttempts->count()} معاملة معلقة. جاري التحقق من البنك...");

        $reconciled = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($pendingAttempts as $attempt) {
            $this->line('');
            $this->info("━━━ معاملة #{$attempt->id} ━━━");
            $this->line("المستخدم: {$attempt->user_id}");
            $this->line("المبلغ: {$attempt->amount} SAR");
            $this->line("الحالة الحالية: {$attempt->status}");
            $this->line("تاريخ الإنشاء: {$attempt->created_at}");

            $transactionId = $attempt->gateway_transaction_id;
            $trackId = $attempt->gateway_checkout_id;
            $amount = (float) $attempt->amount;

            if (blank($transactionId) && blank($trackId)) {
                $this->warn("⏭ لا يوجد معرّف معاملة أو تتبع. تُخطّى.");

                if (! $dryRun && $attempt->status === PaymentAttempt::STATUS_INITIATED) {
                    $this->markAttemptExpired($attempt, 'لم يتم إرسال المعاملة للبوابة (بقيت في حالة initiated).');
                    $failed++;
                } else {
                    $unchanged++;
                }
                continue;
            }

            $queryResult = $this->queryBankStatus($transactionId, $trackId, $amount);

            if ($queryResult === null) {
                $this->warn("⚠ لم يتم الحصول على رد من البنك.");
                $unchanged++;
                continue;
            }

            $status = $this->resolvePayloadStatus($queryResult);
            $paidAmount = $this->resolveAmount($queryResult);

            $this->line("نتيجة البنك: " . ($queryResult['result'] ?? $queryResult['Result'] ?? 'غير معروف'));
            $this->line("الحالة المُحللة: " . ($status ?? 'غير محددة'));
            $this->line("المبلغ المدفوع: {$paidAmount} SAR");

            Log::info('UrPay reconciliation: bank query result.', [
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'status_resolved' => $status,
                'paid_amount' => $paidAmount,
                'bank_response' => $queryResult,
            ]);

            if ($dryRun) {
                $this->comment("🔍 [Dry Run] لن يتم تطبيق أي تغيير.");
                $unchanged++;
                continue;
            }

            if ($status === 'success') {
                if ($amount > 0 && ($paidAmount <= 0 || abs($amount - $paidAmount) > 0.01)) {
                    $this->error("❌ المبلغ المدفوع ({$paidAmount}) لا يطابق المتوقع ({$amount}). تُحوَّل لفشل.");

                    $this->markAttemptFailed($attempt, "المبلغ المدفوع ({$paidAmount}) لا يطابق المبلغ المتوقع ({$amount}) عند التسوية.", $queryResult);
                    $failed++;
                    continue;
                }

                $finalized = $this->finalizePayment($attempt, $queryResult);
                if ($finalized) {
                    $this->info("✅ تم تأكيد الدفع وتسوية المعاملة بنجاح.");
                    $reconciled++;
                } else {
                    $this->error("❌ فشل في تسوية المعاملة.");
                    $failed++;
                }
            } elseif ($status === 'cancel') {
                $this->markAttemptCancelled($attempt, $queryResult);
                $this->warn("🚫 العميل ألغى العملية. تم تحديث الحالة.");
                $failed++;
            } elseif ($status === 'failure') {
                $this->markAttemptFailed($attempt, $queryResult['errorText'] ?? $queryResult['result'] ?? 'فشل الدفع', $queryResult);
                $this->warn("❌ الدفع فشل. تم تحديث الحالة.");
                $failed++;
            } else {
                $this->warn("❓ حالة غير معروفة. لم يتم التغيير.");
                $unchanged++;
            }
        }

        $this->line('');
        $this->info("━━━ ملخص التسوية ━━━");
        $this->info("✅ تم تسويتها: {$reconciled}");
        $this->info("❌ فشل/إلغاء: {$failed}");
        $this->info("⏸ بدون تغيير: {$unchanged}");

        return self::SUCCESS;
    }

    private function queryBankStatus(?string $transactionId, ?string $trackId, float $amount): ?array
    {
        $tranportalId = trim((string) config('urpay.tranportal_id'));
        $tranportalPassword = trim((string) config('urpay.tranportal_password'));
        $baseUrl = rtrim((string) config('urpay.base_url'), '/');
        $verifyOrderPath = trim((string) config('urpay.verify_order_path'), '/');
        $resourceKey = trim((string) config('urpay.token'));

        if ($tranportalId === '' || $tranportalPassword === '' || $baseUrl === '' || $verifyOrderPath === '' || $resourceKey === '') {
            $this->error('إعدادات UrPay غير مكتملة للاستعلام.');
            return null;
        }

        $currencyCode = match (strtoupper(config('urpay.currency', 'SAR'))) {
            'SAR' => '682',
            'KWD' => '414',
            'BHD' => '048',
            'AED' => '784',
            'QAR' => '634',
            'OMR' => '512',
            default => strtoupper(config('urpay.currency', 'SAR')),
        };

        $plainTrandata = [[
            'amt' => number_format($amount, 2, '.', ''),
            'action' => '8',
            'password' => $tranportalPassword,
            'id' => $tranportalId,
            'currencyCode' => $currencyCode,
            'transId' => $transactionId ?? '',
            'trackId' => $trackId ?? '',
        ]];

        try {
            $encrypted = $this->encryptPayload($plainTrandata, $resourceKey);

            $requestBody = [[
                'id' => $tranportalId,
                'trandata' => $encrypted,
            ]];

            $endpoint = $baseUrl . '/' . ltrim($verifyOrderPath, '/');
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post($endpoint, $requestBody);

            if (! $response->successful()) {
                $this->error("استعلام البنك فشل. HTTP {$response->status()}");
                return null;
            }

            $responseData = $response->json();
            if (is_array($responseData) && array_is_list($responseData)) {
                $responseData = $responseData[0] ?? [];
            }

            if (isset($responseData['status']) && (string) $responseData['status'] === '1' && isset($responseData['result'])) {
                return $this->decryptPayload($responseData['result'], $resourceKey);
            }

            if (isset($responseData['trandata'])) {
                return $this->decryptPayload($responseData['trandata'], $resourceKey);
            }
        } catch (\Throwable $e) {
            $this->error("خطأ أثناء استعلام البنك: {$e->getMessage()}");
            Log::error('UrPay reconciliation query error.', [
                'transaction_id' => $transactionId,
                'track_id' => $trackId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function resolvePayloadStatus(array $payload): ?string
    {
        $result = strtolower(trim((string) ($payload['result'] ?? $payload['Result'] ?? '')));
        if ($result === '') {
            return null;
        }

        $cancelKeywords = ['cancel', 'cancelled', 'canceled', 'abort'];
        foreach ($cancelKeywords as $keyword) {
            if (str_contains($result, $keyword)) {
                return 'cancel';
            }
        }

        $failKeywords = ['not captured', 'not-captured', 'not_captured', 'not approved', 'not-approved', 'not_approved', 'unsuccessful', 'fail', 'declin', 'denied', 'reject', 'error', 'expired', 'timeout', 'void'];
        foreach ($failKeywords as $keyword) {
            if (str_contains($result, $keyword)) {
                return 'failure';
            }
        }

        $successKeywords = ['captured', 'approved', 'success', 'successful', 'paid', 'settled'];
        foreach ($successKeywords as $keyword) {
            if (str_contains($result, $keyword)) {
                return 'success';
            }
        }

        return null;
    }

    private function resolveAmount(array $payload): float
    {
        $raw = $payload['amt'] ?? $payload['amount'] ?? $payload['Amt'] ?? $payload['Amount'] ?? null;
        if ($raw !== null && is_numeric(str_replace(',', '', (string) $raw))) {
            return (float) str_replace(',', '', (string) $raw);
        }
        return 0.0;
    }

    private function finalizePayment(PaymentAttempt $attempt, array $bankResponse): bool
    {
        try {
            $userId = $attempt->user_id;
            $cartIds = $attempt->cart_ids ?? [];
            $giftIds = $attempt->gift_ids ?? [];

            // Check if already finalized
            if (! empty($cartIds)) {
                $paidCount = \Modules\Booking\Models\Booking::whereIn('id', $cartIds)->paid()->count();
                if ($paidCount === count($cartIds)) {
                    $this->info("المعاملة مسوّاة مسبقاً (الحجوزات مدفوعة). يتم تحديث الحالة فقط.");
                    app(PaymentAttemptService::class)->markPaid($attempt->id, [
                        'gateway_response' => $bankResponse,
                    ]);
                    return true;
                }
            }

            if (! empty($giftIds) && empty($cartIds)) {
                $paidGifts = \App\Models\GiftCard::whereIn('id', $giftIds)->where('payment_status', 1)->count();
                if ($paidGifts === count($giftIds)) {
                    $this->info("المعاملة مسوّاة مسبقاً (بطاقات الهدايا مدفوعة). يتم تحديث الحالة فقط.");
                    app(PaymentAttemptService::class)->markPaid($attempt->id, [
                        'gateway_response' => $bankResponse,
                    ]);
                    return true;
                }
            }

            $fakeRequest = new Request([
                'wallet' => $attempt->wallet_used,
                'loyalty' => $attempt->loyalty_used,
                'gift_code' => $attempt->gift_code,
                'invoiceCopon' => $attempt->coupon_code,
            ]);

            $subMethodService = app(PaymentSubMethodsService::class);
            $subResult = $subMethodService->apply($userId, $fakeRequest, (float) ($attempt->amount + ($attempt->discount_amount ?? 0)));

            if (isset($subResult['error'])) {
                Log::error('UrPay reconciliation: sub-method error.', [
                    'attempt_id' => $attempt->id,
                    'error' => $subResult['error'],
                ]);
                $this->markAttemptFailed($attempt, 'خطأ في تطبيق وسائل الدفع الفرعية أثناء التسوية: ' . $subResult['error'], $bankResponse);
                return false;
            }

            $finalizer = app(PaymentFinalizerService::class);
            $invoiceId = $finalizer->finalizePayment(
                $userId,
                (float) ($attempt->amount + ($attempt->discount_amount ?? 0)),
                0,
                (float) ($attempt->discount_amount ?? 0),
                $attempt->page ?? 'cart',
                $cartIds,
                $giftIds,
                $attempt->payment_method ?? 'urpay',
                $attempt->coupon_code ?? '',
                true,
                array_merge($subResult, [
                    'gift_code' => $attempt->gift_code,
                    'coupon_discount_amount' => 0,
                    'payment_gateway_discount_amount' => 0,
                    'payment_gateway_discount_method' => null,
                    'payment_gateway_discount_label' => null,
                ]),
                [
                    'attempt_id' => $attempt->id,
                    'transaction_id' => $attempt->gateway_transaction_id,
                    'merchant_reference' => $attempt->merchant_reference,
                    'checkout_id' => $attempt->gateway_checkout_id,
                    'gateway_response' => $bankResponse,
                    'callback_payload' => ['source' => 'reconciliation_command'],
                ]
            );

            $subMethodService->apply($userId, $fakeRequest, (float) ($attempt->amount + ($attempt->discount_amount ?? 0)), true);

            app(PaymentAttemptService::class)->markPaid($attempt->id, [
                'invoice_id' => $invoiceId,
                'gateway_response' => $bankResponse,
                'callback_payload' => ['source' => 'reconciliation_command'],
            ]);

            Log::info('UrPay reconciliation: payment finalized successfully.', [
                'attempt_id' => $attempt->id,
                'invoice_id' => $invoiceId,
                'user_id' => $userId,
                'amount' => $attempt->amount,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('UrPay reconciliation: finalization failed.', [
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("خطأ أثناء التسوية: {$e->getMessage()}");
            return false;
        }
    }

    private function markAttemptFailed(PaymentAttempt $attempt, string $message, array $bankResponse = []): void
    {
        app(PaymentAttemptService::class)->markFailed($attempt->id, $message, array_filter([
            'gateway_response' => $bankResponse ?: null,
            'callback_payload' => ['source' => 'reconciliation_command'],
        ]));

        Log::info('UrPay reconciliation: attempt marked as failed.', [
            'attempt_id' => $attempt->id,
            'message' => $message,
        ]);
    }

    private function markAttemptCancelled(PaymentAttempt $attempt, array $bankResponse = []): void
    {
        app(PaymentAttemptService::class)->markCancelled($attempt->id, 'ألغاها العميل (اكتُشفت عند التسوية).', array_filter([
            'gateway_response' => $bankResponse ?: null,
            'callback_payload' => ['source' => 'reconciliation_command'],
        ]));

        Log::info('UrPay reconciliation: attempt marked as cancelled.', [
            'attempt_id' => $attempt->id,
        ]);
    }

    private function markAttemptExpired(PaymentAttempt $attempt, string $message): void
    {
        app(PaymentAttemptService::class)->markFailed($attempt->id, $message, [
            'callback_payload' => ['source' => 'reconciliation_command'],
        ]);

        Log::info('UrPay reconciliation: initiated attempt marked as expired.', [
            'attempt_id' => $attempt->id,
        ]);
    }

    private function encryptPayload(array $payload, string $resourceKey): string
    {
        $plainJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $blockSize = openssl_cipher_iv_length('aes-256-cbc');
        $pad = $blockSize - (strlen($plainJson) % $blockSize);
        $padded = $plainJson . str_repeat(chr($pad), $pad);

        $encrypted = openssl_encrypt(
            $padded,
            'aes-256-cbc',
            $resourceKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            'PGKEYENCDECIVSPC'
        );

        return urlencode(bin2hex($encrypted));
    }

    private function decryptPayload(string $encryptedHex, string $resourceKey): ?array
    {
        $hex = trim(urldecode($encryptedHex));
        if ($hex === '') {
            return null;
        }

        $binary = @hex2bin($hex);
        if ($binary === false) {
            return null;
        }

        $decrypted = openssl_decrypt(
            $binary,
            'aes-256-cbc',
            $resourceKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            'PGKEYENCDECIVSPC'
        );

        if ($decrypted === false) {
            return null;
        }

        // PKCS5 Unpad
        $length = strlen($decrypted);
        if ($length === 0) {
            return null;
        }
        $pad = ord($decrypted[$length - 1]);
        if ($pad < 1 || $pad > 16) {
            return null;
        }
        if (substr($decrypted, -1 * $pad) !== str_repeat(chr($pad), $pad)) {
            return null;
        }
        $unpadded = substr($decrypted, 0, $length - $pad);

        $payload = json_decode($unpadded, true);
        if (! is_array($payload)) {
            $payload = json_decode(urldecode($unpadded), true);
        }

        if (! is_array($payload) && (str_contains($unpadded, '=') || str_contains($unpadded, '&'))) {
            parse_str($unpadded, $parsed);
            if (is_array($parsed) && ! empty($parsed)) {
                $payload = $parsed;
            }
        }

        if (! is_array($payload)) {
            return null;
        }

        if (array_is_list($payload)) {
            $payload = $payload[0] ?? null;
        }

        return is_array($payload) ? $payload : null;
    }
}
