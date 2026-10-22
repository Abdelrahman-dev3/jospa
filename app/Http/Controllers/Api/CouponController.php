<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\UserCouponRedeem;

class CouponController extends Controller
{
    public function availableCoupons(Request $request)
    {
        $coupons = Coupon::with('promotion')->usable()->get();
        
        return response()->json([
            'status' => true,
            'data' => $coupons,
            'message' => __('promotion.coupons_list'),
        ], 200);
    }

    public function validateCoupon(Request $request)
    {
        $couponCode = trim((string) $request->query('coupon_code')) ?: trim((string) $request->query('code'));
        $serviceId = $request->query('service_id');
        $bookingId = $request->query('booking_id');

        $coupon = Coupon::query()->where('coupon_code', $couponCode)->usable()->first();

        $services = $this->normalizeServices($coupon?->services ?? []);
        $serviceId = (int) $serviceId;

        if ($coupon && in_array($serviceId, $services, true)) {
            // Check specific dates
            if (! $coupon->isAvailableToday()) {
                return response()->json(['valid' => false, 'message' => __('messagess.coupon_not_available_today', [], app()->getLocale()) ?: 'This coupon is not available today']);
            }

            $userId = auth()->id() ?? auth('sanctum')->id();
            // The cart/payment flow scopes bookings by created_by (see Booking::userBaseQuery)
            $ownsBooking = $userId && Booking::where('id', $bookingId)
                ->where(fn ($q) => $q->where('created_by', $userId)->orWhere('user_id', $userId))
                ->exists();

            if (!$ownsBooking) {
                return response()->json(['valid' => false]);
            }

            // Lock the row so concurrent/repeated requests can't apply the coupon twice,
            // and target the requested service instead of any service in the booking.
            $applied = DB::transaction(function () use ($bookingId, $serviceId, $coupon, $couponCode, $userId) {
                $bookingService = BookingService::where('booking_id', $bookingId)
                    ->where('service_id', $serviceId)
                    ->whereNull('coupon_code')
                    ->lockForUpdate()
                    ->first();

                if (!$bookingService) {
                    return false;
                }

                $price = $bookingService->service_price ?? 0;
                $discountAmount = $coupon->discount_type === 'percent'
                    ? ($price * $coupon->discount_percentage / 100)
                    : $coupon->discount_amount;
                $discountAmount = min((float) $discountAmount, (float) $price);

                $bookingService->update([
                    'coupon_code' => $coupon->coupon_code,
                    'discount_amount' => $discountAmount,
                ]);

                UserCouponRedeem::create([
                    'user_id' => $userId,
                    'coupon_code' => $couponCode,
                    'discount' => $discountAmount,
                    'coupon_id' => $coupon->id,
                    'booking_id' => $bookingId,
                ]);

                return true;
            });

            if (!$applied) {
                return response()->json(['valid' => false]);
            }

            $coupon->syncExpiredState();

            return response()->json(['valid' => true]);
        }

        return response()->json(['valid' => false]);
    }

    public function validateInvoiceCoupon(Request $request)
    {
        $couponCode = trim((string) $request->query('coupon_code')) ?: trim((string) $request->query('code'));
        $giftCode = trim((string) $request->query('gift_code', ''));

        if ($giftCode !== '') {
            return response()->json([
                'valid' => false,
                'message' => __('messagess.gift_card_coupon_not_allowed'),
            ], 422);
        }

        if (auth()->check()) {
            $hasGiftCardInCart = \App\Models\GiftCard::where('user_id', auth()->id())
                ->where('payment_status', 0)
                ->exists();

            if ($hasGiftCardInCart) {
                return response()->json([
                    'valid' => false,
                    'message' => __('messagess.gift_card_coupon_not_allowed'),
                ], 422);
            }
        }

        $coupon = Coupon::query()
            ->where('coupon_code', $couponCode)
            ->usable()
            ->first();

        if (!$coupon) {
            return response()->json(['valid' => false]);
        }

        // Check specific dates
        if (! $coupon->isAvailableToday()) {
            return response()->json([
                'valid' => false,
                'message' => __('messagess.coupon_not_available_today', [], app()->getLocale()) ?: 'This coupon is not available today',
            ]);
        }

        $services = $this->normalizeServices($coupon->services);
        if (!in_array(0, $services, true)) {
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid' => true,
            'discount_type' => $coupon->discount_type,
            'discount_percentage' => $coupon->discount_percentage ?? 0,
            'discount_amount' => $coupon->discount_amount ?? 0,
        ]);
    }

    private function normalizeServices($services): array
    {
        if (is_array($services)) {
            return array_map('intval', $services);
        }

        if (is_string($services)) {
            $decoded = json_decode($services, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_map('intval', $decoded);
            }

            return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $services)))));
        }

        return [];
    }
}
