<?php

namespace Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Wallet\Models\WalletTopupAttempt;
use Modules\Wallet\Services\WalletTopupService;

/**
 * Browser pages for the mobile-app wallet top-up (opened in a WebView / in-app browser).
 */
class WalletAppTopupController extends Controller
{
    public function __construct(private WalletTopupService $topups)
    {
    }

    /** GET /wallet/app-topup/pay/{attempt} (signed URL from /api/wallet/topup/init). */
    public function pay(Request $request, string $attempt)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $model = WalletTopupAttempt::where('attempt_id', $attempt)->first();

        if (! $model || $model->status !== WalletTopupAttempt::STATUS_PENDING || ! $model->checkout_id) {
            return view('frontend.payment-status.failed', [
                'message' => app()->getLocale() === 'ar'
                    ? 'انتهت جلسة الدفع. حاول مرة أخرى.'
                    : 'Payment session expired. Please try again.',
            ]);
        }

        [$brands, $labels] = match ($model->brand) {
            'MADA' => ['MADA', ['Mada']],
            'APPLEPAY' => ['APPLEPAY', ['Apple Pay']],
            default => ['VISA MASTER', ['Visa', 'Mastercard']],
        };

        return view('frontend.payment-status.hyperpay', [
            'widgetScriptUrl' => $this->topups->widgetScriptUrl($model),
            'brands' => $brands,
            'brandLabels' => $labels,
            'resultUrl' => route('wallet.app-topup.return', ['attempt' => $model->attempt_id]),
        ]);
    }

    /**
     * GET|POST /wallet/app-topup/return/{attempt}
     * HyperPay sends the shopper here. Query params from HyperPay are ignored: the
     * server checks its own checkout id directly with HyperPay.
     */
    public function return(Request $request, string $attempt)
    {
        $model = WalletTopupAttempt::where('attempt_id', $attempt)->first();

        if (! $model) {
            abort(404);
        }

        $model = $this->topups->refresh($model, force: true);

        return redirect()->away($this->finalUrl($model));
    }

    /** GET /wallet/app-topup/done?attempt_id=..&status=.. — the page the app watches for. */
    public function done(Request $request)
    {
        $status = (string) $request->query('status');

        if ($status === WalletTopupAttempt::STATUS_PAID) {
            return view('frontend.payment-status.captured');
        }

        if ($status === WalletTopupAttempt::STATUS_PENDING) {
            return view('frontend.payment-status.failed', [
                'message' => app()->getLocale() === 'ar'
                    ? 'جارٍ تأكيد عملية الدفع. سيتم تحديث رصيدك تلقائيًا.'
                    : 'Your payment is being confirmed. Your balance will update automatically.',
            ]);
        }

        return view('frontend.payment-status.failed', [
            'message' => app()->getLocale() === 'ar' ? 'لم تكتمل عملية الدفع.' : 'The payment was not completed.',
        ]);
    }

    private function finalUrl(WalletTopupAttempt $attempt): string
    {
        $base = trim((string) config('services.wallet_topup.app_return_url', ''));
        if ($base === '') {
            $base = route('wallet.app-topup.done');
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query([
            'attempt_id' => $attempt->attempt_id,
            'status' => $attempt->status,
        ]);
    }
}
