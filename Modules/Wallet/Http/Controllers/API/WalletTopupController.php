<?php

namespace Modules\Wallet\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
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
     * Header (recommended): Idempotency-Key: <uuid, one per top-up the user starts>
     * Body: { "amount": 100, "brand": "VISA" | "MADA" | "APPLEPAY" }
     *
     * Retrying with the same Idempotency-Key returns the same attempt (200, "replayed": true)
     * instead of creating a second checkout.
     */
    public function init(Request $request): JsonResponse
    {
        $min = (float) config('services.wallet_topup.min_amount', 10);
        $max = (float) config('services.wallet_topup.max_amount', 10000);

        $validator = Validator::make(
            $request->all() + ['idempotency_key' => $request->header('Idempotency-Key')],
            [
                'amount' => ['required', 'numeric', 'min:' . $min, 'max:' . $max, 'regex:/^\d+(\.\d{1,2})?$/'],
                'brand' => ['nullable', 'string', 'in:VISA,MASTER,MADA,APPLEPAY,visa,master,mada,applepay'],
                'currency' => ['nullable', 'string', 'in:SAR,sar'],
                'idempotency_key' => ['nullable', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error_code' => 'validation_failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $amount = (float) $request->input('amount');
        $brand = (string) $request->input('brand', 'VISA');
        $idempotencyKey = $request->header('Idempotency-Key') ?: null;

        if ($idempotencyKey && ($existing = $this->findByKey($user->id, $idempotencyKey))) {
            return $this->replay($existing, $amount, $brand);
        }

        try {
            $attempt = $this->topups->initiate($user, $amount, $brand, $idempotencyKey);
        } catch (QueryException $e) {
            // Two concurrent requests with the same key: the unique index rejected the second.
            if ($idempotencyKey && ($existing = $this->findByKey($user->id, $idempotencyKey))) {
                return $this->replay($existing, $amount, $brand);
            }

            throw $e;
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
            'replayed' => false,
            'data' => array_merge($this->topups->presentForApp($attempt), [
                'return_url' => $this->returnUrlPattern(),
            ]),
        ], 201);
    }

    /**
     * GET /api/wallet/topup/status/{attempt_id}
     * While the attempt is still resumable, data.payment_url lets the app reopen the payment page.
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
            'data' => array_merge($this->topups->presentForApp($attempt), [
                'return_url' => $this->returnUrlPattern(),
            ]),
        ]);
    }

    /**
     * GET /api/wallet/topup/latest
     * The user's most recent attempt (any status), e.g. when the app lost the attempt_id.
     * data is null when the user has never started a top-up.
     */
    public function latest(Request $request): JsonResponse
    {
        $attempt = WalletTopupAttempt::where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        if (! $attempt) {
            return response()->json(['status' => true, 'data' => null]);
        }

        $attempt = $this->topups->refresh($attempt);

        return response()->json([
            'status' => true,
            'data' => array_merge($this->topups->presentForApp($attempt), [
                'return_url' => $this->returnUrlPattern(),
            ]),
        ]);
    }

    private function findByKey(int $userId, string $key): ?WalletTopupAttempt
    {
        return WalletTopupAttempt::where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();
    }

    private function replay(WalletTopupAttempt $attempt, float $amount, string $brand): JsonResponse
    {
        if (! WalletTopupService::matchesRequest($attempt, $amount, $brand)) {
            return response()->json([
                'status' => false,
                'error_code' => 'idempotency_key_reused',
                'message' => 'This Idempotency-Key was already used for a different amount or brand.',
            ], 409);
        }

        $attempt = $this->topups->refresh($attempt);

        return response()->json([
            'status' => true,
            'message' => 'Existing payment session returned.',
            'replayed' => true,
            'data' => array_merge($this->topups->presentForApp($attempt), [
                'return_url' => $this->returnUrlPattern(),
            ]),
        ], 200);
    }

    private function returnUrlPattern(): string
    {
        $deepLink = trim((string) config('services.wallet_topup.app_return_url', ''));

        return ($deepLink !== '' ? $deepLink : route('wallet.app-topup.done')) . '?attempt_id={attempt_id}&status={status}';
    }
}
