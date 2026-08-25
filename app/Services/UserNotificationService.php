<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserAccountNotification;
use Illuminate\Support\Facades\Log;

class UserNotificationService
{
    /**
     * Send notification when a booking is paid.
     */
    public function notifyBookingPaid(User $user, $booking): void
    {
        $this->send($user, [
            'type' => 'booking_paid',
            'title' => __('notifications.booking_paid_title'),
            'message' => __('notifications.booking_paid_message', ['id' => $booking->id]),
            'url' => route('profile.my_bookings'),
            'icon' => 'booking',
        ]);
    }

    /**
     * Send notification when a package is purchased.
     */
    public function notifyPackagePurchased(User $user, string $packageName): void
    {
        $this->send($user, [
            'type' => 'package_purchased',
            'title' => __('notifications.package_purchased_title'),
            'message' => __('notifications.package_purchased_message', ['name' => $packageName]),
            'url' => route('profile.my_bookings'),
            'icon' => 'package',
        ]);
    }

    /**
     * Send notification when a gift card is purchased.
     */
    public function notifyGiftCardPurchased(User $user, int $count = 1): void
    {
        $this->send($user, [
            'type' => 'gift_card_purchased',
            'title' => __('notifications.gift_card_purchased_title'),
            'message' => $count > 1
                ? __('notifications.gift_cards_purchased_message', ['count' => $count])
                : __('notifications.gift_card_purchased_message'),
            'url' => route('profile.complateGift'),
            'icon' => 'gift',
        ]);
    }

    /**
     * Send notification when loyalty points are added.
     */
    public function notifyLoyaltyPointsAdded(User $user, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $this->send($user, [
            'type' => 'loyalty_points_added',
            'title' => __('notifications.loyalty_points_added_title'),
            'message' => __('notifications.loyalty_points_added_message', ['points' => $points]),
            'url' => route('home.loyalety'),
            'icon' => 'loyalty',
        ]);
    }

    /**
     * Send notification when wallet is topped up.
     */
    public function notifyWalletTopUp(User $user, float $amount): void
    {
        $this->send($user, [
            'type' => 'wallet_topup',
            'title' => __('notifications.wallet_topup_title'),
            'message' => __('notifications.wallet_topup_message', ['amount' => number_format($amount, 2)]),
            'url' => route('profile'),
            'icon' => 'wallet',
        ]);
    }

    /**
     * Send notification when booking status changes.
     */
    public function notifyBookingStatusChanged(User $user, $booking, string $status): void
    {
        $statusLabels = [
            'confirmed' => __('notifications.status_confirmed'),
            'check_in' => __('notifications.status_check_in'),
            'checkout' => __('notifications.status_checkout'),
            'completed' => __('notifications.status_completed'),
            'cancelled' => __('notifications.status_cancelled'),
        ];

        $statusLabel = $statusLabels[$status] ?? $status;

        $this->send($user, [
            'type' => 'booking_status_changed',
            'title' => __('notifications.booking_status_changed_title'),
            'message' => __('notifications.booking_status_changed_message', [
                'id' => $booking->id,
                'status' => $statusLabel,
            ]),
            'url' => route('profile.my_bookings'),
            'icon' => 'booking',
        ]);
    }

    /**
     * Send notification when wallet is debited for a booking.
     */
    public function notifyWalletDebit(User $user, float $amount): void
    {
        $this->send($user, [
            'type' => 'wallet_debit',
            'title' => __('notifications.wallet_debit_title'),
            'message' => __('notifications.wallet_debit_message', ['amount' => number_format($amount, 2)]),
            'url' => route('profile'),
            'icon' => 'wallet',
        ]);
    }

    /**
     * Send notification when a coupon is applied.
     */
    public function notifyCouponApplied(User $user, float $discount): void
    {
        $this->send($user, [
            'type' => 'coupon_applied',
            'title' => __('notifications.coupon_applied_title'),
            'message' => __('notifications.coupon_applied_message', ['amount' => number_format($discount, 2)]),
            'url' => route('profile.coupon'),
            'icon' => 'coupon',
        ]);
    }

    /**
     * Send welcome notification to new user.
     */
    public function notifyWelcome(User $user): void
    {
        $this->send($user, [
            'type' => 'welcome',
            'title' => __('notifications.welcome_title'),
            'message' => __('notifications.welcome_message', ['name' => $user->first_name]),
            'url' => '/',
            'icon' => 'welcome',
        ]);
    }

    /**
     * Send notification when package is about to expire.
     */
    public function notifyPackageExpiring(User $user, string $packageName): void
    {
        $this->send($user, [
            'type' => 'package_expiring',
            'title' => __('notifications.package_expiring_title'),
            'message' => __('notifications.package_expiring_message', ['name' => $packageName]),
            'url' => route('frontend.Packages'),
            'icon' => 'package',
        ]);
    }

    /**
     * Internal method to dispatch the notification safely.
     */
    protected function send(User $user, array $data): void
    {
        try {
            $user->notify(new UserAccountNotification(
                $data['type'],
                $data['title'],
                $data['message'],
                $data['url'] ?? '/',
                $data['icon'] ?? 'default'
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send user account notification.', [
                'user_id' => $user->id,
                'type' => $data['type'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
