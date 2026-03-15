<?php

namespace App\Services\Payment;
use Modules\Booking\Models\Booking;
use Modules\Product\Models\Cart;
use App\Models\LoyaltyPoint;
use App\Models\GiftCard;
use Modules\Promotion\Models\Coupon;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class PaymentCalculatorService
{
    public function calculateTotal(string $typePage, ?string $couponCode = null): array
    {
        $total = 0;
        $productTotal = 0;
        $userId = auth()->id();
        $cartIds    = [];
        $giftIds    = [];
        $productIds = [];
        
        if ($typePage === 'payment') {
            $services = Booking::getUserIncompleteBookings($userId, 'payment', ['service.service']);

            
            $cartIds = $services->pluck('id')->toArray();

            $total += $services->sum(fn($item) =>
                ($item->service->service_price ?? 0) - ($item->service->discount_amount ?? 0)
            );
        } else {
            $services = Booking::getUserIncompleteBookings($userId, 'cart', ['service.service']);

            $products = Cart::with('product')->where('user_id', $userId)->get();
            $gifts    = GiftCard::where('user_id', $userId)->where('payment_status', 0)->get();
            
            $productIds = $products->pluck('product_id')->toArray();
            $giftIds    = $gifts->pluck('id')->toArray();
            $cartIds    = $services->pluck('id')->toArray();

            $total += $services->sum(fn($item) =>
                ($item->service->service_price ?? 0)
                - ($item->service->discount_amount ?? 0)
            );

            $productTotal = $products->sum(fn($item) =>
                (($item->product->max_price ?? $item->product->min_price) ?? 0)
                * ($item->qty ?? 1)
            );

            $total += $productTotal;
            $total += $gifts->sum(fn($g) => $g->subtotal ?? 0);
        }

        $tax = getBookingTaxamount($total, 0, null)['total_tax_amount'] + getTaxamount($productTotal)['total_tax_amount'];

        $finalTotal = $total + $tax;
        $discount = 0;

        if ($couponCode  && $couponCode != '') {
            $coupon = Coupon::where('coupon_code', $couponCode)->where('is_expired', 0)->first();
            if (! $coupon || ! $coupon->isWithinActiveDateRange() || ! $coupon->hasRemainingUses()) {
                if ($coupon) {
                    $coupon->syncExpirationStatus();
                }

                return [
                    'error' => __('messages.invalid_coupon')
                ];
            }
            
            $discount = $coupon->discount_type === 'percent'
                ? ($finalTotal * $coupon->discount_percentage) / 100
                : $coupon->discount_amount;

            $finalTotal = max($finalTotal - $discount, 0);
        }

        return [
            'total' => $finalTotal,
            'discountAmount' => $discount,
            'tax' => $tax,
            'cart_ids'      => $cartIds,
            'gift_ids'      => $giftIds,
            'product_ids'   => $productIds,
        ];
    }
}
