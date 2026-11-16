<?php

namespace Modules\Wallet\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Wallet\Models\WalletTopupAttempt;
use Modules\Wallet\Services\WalletTopupService;

class WalletTopupController extends Controller
{
    public function __construct(private WalletTopupService $topups)
    {
    }

    /**
     * POST /api/wallet/topup/init
     * Body: { "amount": 100, "brand": "VISA" | "MADA" | "APPLEPAY" }
     */
    public function init(Request $request): JsonResponse
    {
        $min = (float) config('services.wallet_topup.min_amount', 10);
        $max = (float) config('services.wallet_topup.max_amount', 10000);

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . $min, 'max:' . $max, 'regex:/^\d+(\.\d{1,2})?$/'],
            'brand' => ['nullable', 'string', 'in:VISA,MASTER,MADA,APPLEPAY,visa,master,mada,applepay'],
            'currency' => ['nullable', 'string', 'in:SAR,sar'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error_code' => 'validation_failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $attempt = $this->topups->initiate(
                $request->user(),
                (float) $request->input('amount'),
                (string) $request->input('brand', 'VISA')
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'error_code' => 'gateway_unavailable',
                'message' => app()->getLocale() === 'ar'
                    ? 'تعذر بدء عملية الدفع حاليًا. حاول مرة أخرى.'
                    : 'Could not start the payment. Please try again.',
            ], 502);
        }

        return response()->json([
            'status' => true,
            'message' => 'Payment session created.',
            'data' => array_merge($this->topups->present($attempt), [
                'payment_url' => $this->topups->paymentUrl($attempt),
                'return_url' => $this->returnUrlPattern(),
            ]),
        ], 201);
    }

    /**
     * GET /api/wallet/topup/status/{attempt_id}
     */
    public function status(Request $request, string $attemptId): JsonResponse
    {
        $attempt = WalletTopupAttempt::where('attempt_id', $attemptId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $attempt) {
            return response()->json([
                'status' => false,
                'error_code' => 'attempt_not_found',
                'message' => 'Top-up attempt not found.',
            ], 404);
        }

        $attempt = $this->topups->refresh($attempt);

        return response()->json([
            'status' => true,
            'data' => $this->topups->present($attempt),
        ]);
    }

    private function returnUrlPattern(): string
    {
        $deepLink = trim((string) config('services.wallet_topup.app_return_url', ''));

        return ($deepLink !== '' ? $deepLink : route('wallet.app-topup.done')) . '?attempt_id={attempt_id}&status={status}';
    }
}
