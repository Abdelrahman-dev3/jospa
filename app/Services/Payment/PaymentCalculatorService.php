<?php

namespace App\Services\Payment;

use App\Support\FrontendPaymentSettings;
use Modules\Booking\Models\Booking;
use Modules\Product\Models\Cart;
use App\Models\GiftCard;
use Modules\Promotion\Models\Coupon;

class PaymentCalculatorService
{
    public function calculateTotal(string $typePage, ?string $couponCode = null, ?string $paymentMethod = null, ?int $userId = null): array
    {
        $total = 0;
        $productTotal = 0;
        $userId ??= auth()->id();
        $cartIds    = [];
        $giftIds    = [];
        $productIds = [];

        if (! $userId) {
            return [
                'error' => __('messages.user_notfound'),
            ];
        }
        
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

        $grossTotal = $total + $tax;
        $couponDiscount = 0;

        if ($couponCode  && $couponCode != '') {
            $coupon = Coupon::query()
                ->where('coupon_code', $couponCode)
                ->usable()
                ->first();

            if (!$coupon) {
                return [
                    'error' => __('messages.invalid_coupon')
                ];
            }

            $coupon->syncExpiredState();

            $couponDiscount = $coupon->discount_type === 'percent'
                ? ($grossTotal * $coupon->discount_percentage) / 100
                : $coupon->discount_amount;
        }

        $couponDiscount = min($couponDiscount, $grossTotal);

        $grossAfterCoupon = max($grossTotal - $couponDiscount, 0);
        $paymentGatewayDiscount = min(
            FrontendPaymentSettings::paymentGatewayDiscountAmount($paymentMethod, $grossAfterCoupon),
            $grossAfterCoupon
        );
        $finalTotal = max($grossAfterCoupon - $paymentGatewayDiscount, 0);
        $discount = $couponDiscount + $paymentGatewayDiscount;

        return [
            'total' => $finalTotal,
            'discountAmount' => $discount,
            'couponDiscountAmount' => $couponDiscount,
            'paymentGatewayDiscountAmount' => $paymentGatewayDiscount,
            'paymentGatewayDiscountMethod' => $paymentGatewayDiscount > 0 ? $paymentMethod : null,
            'paymentGatewayDiscountLabel' => $paymentGatewayDiscount > 0
                ? FrontendPaymentSettings::paymentGatewayDiscountLabel($paymentMethod)
                : null,
            'tax' => $tax,
            'cart_ids'      => $cartIds,
            'gift_ids'      => $giftIds,
            'product_ids'   => $productIds,
        ];
    }
}
