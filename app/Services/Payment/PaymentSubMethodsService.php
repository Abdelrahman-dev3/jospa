<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHistory;
use App\Models\LoyaltyPoint;
use App\Models\GiftCard;
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
        $giftError = false;

        $isWallet   = (bool)$request->wallet;
        $isLoyalty  = (bool)$request->loyalty;
        $isGiftCode = !empty($request->gift_code);

        DB::transaction(function () use (&$final, &$usedWallet, &$usedLoyalty, &$usedGift, &$giftError, $userId, $isWallet, $isLoyalty, $isGiftCode, $request, $commit) {

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
                        'user_id'          => auth()->id(),
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
            if ($isGiftCode && $final > 0) {
                $gift = GiftCard::where('ref', $request->gift_code)
                    ->where('payment_status', 1)
                    ->lockForUpdate()
                    ->first();
                if (!$gift) {
                    $giftError = true;
                    return;
                }

                $usedGift = min($gift->balance, $final);
                if ($commit) {
                    $gift->balance -= $usedGift;
                    $gift->save();
                }
                $final -= $usedGift;
            }
        });

        if ($giftError) {
            return ['error' => __('messagess.invalid_gift_code')];
        }

        return [
            'remaining_amount' => max($final, 0),
            'used_wallet'      => $usedWallet,
            'used_loyalty'     => $usedLoyalty,
            'used_gift'        => $usedGift,
        ];
    }
}
