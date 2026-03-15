<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Booking\Models\BookingService;
use Modules\Promotion\Models\Coupon;

class CouponController extends Controller
{
    public function availableCoupons(Request $request)
    {
        $coupons = Coupon::with('promotion')
            ->where('is_expired', 0)
            ->get()
            ->filter(function (Coupon $coupon) {
                if (! $coupon->isWithinActiveDateRange() || ! $coupon->hasRemainingUses()) {
                    $coupon->syncExpirationStatus();

                    return false;
                }

                return true;
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $coupons,
            'message' => __('promotion.coupons_list'),
        ], 200);
    }

    public function validateCoupon(Request $request)
    {
        $couponCode = $request->query('coupon_code');
        $serviceId = (int) $request->query('service_id');
        $bookingId = $request->query('booking_id');

        $coupon = Coupon::where('coupon_code', $couponCode)
            ->where('is_expired', 0)
            ->first();

        if (! $coupon || ! $coupon->isWithinActiveDateRange() || ! $coupon->hasRemainingUses()) {
            if ($coupon) {
                $coupon->syncExpirationStatus();
            }

            return response()->json(['valid' => false]);
        }

        $services = $this->normalizeServices($coupon->services ?? []);

        if (in_array($serviceId, $services, true)) {
            $bookingService = BookingService::where('booking_id', $bookingId)
                ->where('service_id', $serviceId)
                ->whereNull('coupon_code')
                ->first();

            if (! $bookingService) {
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

            return response()->json(['valid' => true]);
        }

        return response()->json(['valid' => false]);
    }

    public function validateInvoiceCoupon(Request $request)
    {
        $couponCode = $request->query('coupon_code');

        $coupon = Coupon::where('coupon_code', $couponCode)
            ->where('is_expired', 0)
            ->first();

        if (! $coupon || ! $coupon->isWithinActiveDateRange() || ! $coupon->hasRemainingUses()) {
            if ($coupon) {
                $coupon->syncExpirationStatus();
            }

            return response()->json(['valid' => false]);
        }

        $services = $this->normalizeServices($coupon->services);

        if (! in_array(0, $services, true)) {
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
            return $services;
        }

        if (is_string($services)) {
            $decoded = json_decode($services, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $services)))));
        }

        return [];
    }
}
