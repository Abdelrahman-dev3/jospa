<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHistory;
use App\Models\LoyaltyPoint;
use App\Services\OdooGiftCardService;
use Illuminate\Support\Facades\DB;
use App\Models\LoyaltyPointTransaction;

class PaymentSubMethodsService
{
    /**
     * Apply wallet, loyalty, and gift card payments
     */
    public function apply(int $userId, Request $request, float $amount, bool $commit = false): array
    {
        $final = $amount;
        $usedWallet = $usedLoyalty = $usedGift = 0;
        $giftBalance = null;

        $isWallet   = (bool)$request->wallet;
        $isLoyalty  = (bool)$request->loyalty;
        $isGiftCode = !empty($request->gift_code);
        $requestedGiftAmount = $this->requestedGiftAmount($request);
        $shouldUseGift = $isGiftCode && ($requestedGiftAmount > 0 || ! $request->has('gift_amount'));

        if ($shouldUseGift) {
            $result = app(OdooGiftCardService::class)->check((string) $request->gift_code);
            $giftBalance = (float) ($result['balance'] ?? 0);

            if (! ($result['valid'] ?? false) || $giftBalance <= 0) {
                return [
                    'error' => $result['message'] ?? __('messagess.invalid_gift_code'),
                    'gift_balance' => $giftBalance,
                ];
            }

            if ($requestedGiftAmount > 0 && $requestedGiftAmount - $giftBalance > 0.01) {
                return [
                    'error' => 'Insufficient gift card balance.',
                    'gift_balance' => $giftBalance,
                ];
            }
        }

        DB::transaction(function () use (&$final, &$usedWallet, &$usedLoyalty, &$usedGift, $userId, $isWallet, $isLoyalty, $shouldUseGift, $requestedGiftAmount, $giftBalance, $commit) {

            // Wallet
            if ($isWallet && $final > 0) {
                $wallet = Wallet::where('user_id', $userId)->where('status', 1)->lockForUpdate()->first();
                if ($wallet && $wallet->amount > 0) {
                    $usedWallet = min($wallet->amount, $final);
                    if ($commit) {
                        $wallet->amount -= $usedWallet;
                        $wallet->save();
                        WalletHistory::create([
                        'datetime'         => now(),
                        'user_id'          => $userId,
                        'activity_type'    => 'withdraw',
                        'activity_message' => 'Wallet balance withdraw',
                        'activity_data'    => json_encode([
                            'credit_debit_amount' => $usedWallet,
                        ]),
                    ]);

                    }
                    $final -= $usedWallet;
                }
            }

            // Loyalty
            if ($isLoyalty && $final > 0) {
                $rate = \App\Models\Setting::get('point_value') ?? 0.5;
                $loyalty = LoyaltyPoint::where('user_id', $userId)->lockForUpdate()->first();
                if ($loyalty && $loyalty->points > 0) {
                    $maxUse = $loyalty->points * $rate;
                    $used = min($final, $maxUse);
                    $pointsUsed = ceil($used / $rate);
                    if ($commit) {
                        $loyalty->points -= $pointsUsed;
                        $loyalty->save();
                        LoyaltyPointTransaction::create([
                            'user_id' => $userId,
                            'action' => 'deduct',
                            'points' => $pointsUsed,
                            'balance_after' => $loyalty->points,
                            'source' => 'خصم من خلال وسيلة نقاط الولاء',
                        ]);
                    }
                    $usedLoyalty = $pointsUsed;
                    $final -= $used;
                }
            }

            // Gift Card
            if ($shouldUseGift && $final > 0) {
                $usedGift = $requestedGiftAmount > 0
                    ? min($requestedGiftAmount, $final)
                    : min($giftBalance, $final);

                $final -= $usedGift;
            }
        });

        return [
            'remaining_amount' => max($final, 0),
            'used_wallet'      => $usedWallet,
            'used_loyalty'     => $usedLoyalty,
            'used_gift'        => $usedGift,
        ];
    }

    private function requestedGiftAmount(Request $request): float
    {
        $value = $request->input('gift_amount', $request->input('used_gift'));

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return max((float) $value, 0.0);
    }
}
