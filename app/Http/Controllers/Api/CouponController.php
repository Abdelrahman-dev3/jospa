<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Booking\Models\BookingService;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\UserCouponRedeem;

class CouponController extends Controller
{
    public function validateCoupon(Request $request)
    {
        $couponCode = $request->query('coupon_code');
        $serviceId = $request->query('service_id');
        $bookingId = $request->query('booking_id');

        $coupon = Coupon::where('coupon_code', $couponCode)
            ->where('is_expired', 0)
            ->where('use_limit', '>=', 1)
            ->first();

        if ($coupon && in_array((int) $serviceId, $coupon->services)) {
            $bookingService = BookingService::where('booking_id', $bookingId)->whereNull('coupon_code')->first();

            if (!$bookingService) {
                return response()->json(['valid' => false]);
            }

            $price = $bookingService->service_price ?? 0;
            $discountAmount = $coupon->discount_type === 'percent'
                ? ($price * $coupon->discount_percentage / 100)
                : $coupon->discount_amount;

            $bookingService->update([
                'coupon_code' => $coupon->coupon_code,
                'discount_amount' => $discountAmount,
            ]);

            $coupon->decrement('use_limit');

            UserCouponRedeem::create([
                'user_id' => auth()->id(),
                'coupon_code' => $couponCode,
                'discount' => $discountAmount,
                'coupon_id' => $coupon->id,
                'booking_id' => $bookingId,
            ]);

            return response()->json(['valid' => true]);
        }

        return response()->json(['valid' => false]);
    }

    public function validateInvoiceCoupon(Request $request)
    {
        $couponCode = $request->query('coupon_code');

        $coupon = Coupon::where('coupon_code', $couponCode)
            ->where('is_expired', 0)
            ->where('use_limit', '>=', 1)
            ->first();

        if (!$coupon) {
            return response()->json(['valid' => false]);
        }

        $services = $coupon->services;
        if (!in_array(0, $services)) {
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid' => true,
            'discount_type' => $coupon->discount_type,
            'discount_percentage' => $coupon->discount_percentage ?? 0,
            'discount_amount' => $coupon->discount_amount ?? 0,
        ]);
    }
}
