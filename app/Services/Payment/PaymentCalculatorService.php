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
        $couponCode = trim((string) $couponCode);
        $cartIds    = [];
        $giftIds    = [];
        $productIds = [];

        if (! $userId) {
            return [
                'error' => __('messages.user_notfound'),
            ];
        }
        
        if ($typePage === 'payment') {
            $services = Booking::getUserIncompleteBookings($userId, 'payment', ['services.service']);

            
            $cartIds = $services->pluck('id')->toArray();

            $total += $services->sum(fn($item) =>
                $item->services->sum(fn($s) => ($s->service_price ?? 0) - ($s->discount_amount ?? 0))
            );
        } else {
            $services = Booking::getUserIncompleteBookings($userId, 'cart', ['services.service']);

            $products = Cart::with('product')->where('user_id', $userId)->get();
            $gifts    = GiftCard::where('user_id', $userId)->where('payment_status', 0)->get();
            
            $productIds = $products->pluck('product_id')->toArray();
            $giftIds    = $gifts->pluck('id')->toArray();
            $cartIds    = $services->pluck('id')->toArray();

            $total += $services->sum(fn($item) =>
                $item->services->sum(fn($s) => ($s->service_price ?? 0) - ($s->discount_amount ?? 0))
            );

            $productTotal = $products->sum(fn($item) =>
                (($item->product->max_price ?? $item->product->min_price) ?? 0)
                * ($item->qty ?? 1)
            );

            $total += $productTotal;
            $total += $gifts->sum(fn($g) => $g->subtotal ?? 0);
        }

        // FIX: Calculate booking tax on services+gifts only (excluding products),
        // then add product tax separately. Previously $total (which already included
        // products) was passed to getBookingTaxamount AND getTaxamount($productTotal)
        // was added again, causing product tax to be counted twice — inflating the
        // amount sent to the payment gateway above what was shown to the customer.
        $serviceAndGiftTotal = $total - $productTotal;
        $tax = getBookingTaxamount($serviceAndGiftTotal, 0, null)['total_tax_amount']
             + getTaxamount($productTotal)['total_tax_amount'];

        $grossTotal = $total + $tax;
        $couponDiscount = 0;

        // A coupon already applied per-service (via validate-coupon) is reflected in
        // the service discount_amount subtracted above; applying it again on the
        // invoice total would count the same coupon twice.
        $serviceCouponCodes = $services
            ->flatMap(fn($item) => $item->services->pluck('coupon_code'))
            ->filter()
            ->map(fn($code) => strtolower(trim($code)))
            ->unique();

        if ($couponCode && $serviceCouponCodes->contains(strtolower(trim($couponCode)))) {
            $couponCode = null;
        }

        if ($couponCode  && $couponCode != '') {
            if ($giftIds !== []) {
                return ['error' => __('messagess.gift_card_coupon_not_allowed')];
            }

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

            // Invoice coupons must explicitly include the invoice-wide service marker.
            $eligibleServices = $coupon->services ?? [];
            if (is_string($eligibleServices)) {
                $eligibleServices = json_decode($eligibleServices, true) ?? explode(',', $eligibleServices);
            }
            if (!is_array($eligibleServices) || !in_array(0, array_map('intval', $eligibleServices), true)) {
                return ['error' => __('messages.invalid_coupon')];
            }

            // Branch-restricted coupons: every booking being paid must be in an allowed branch.
            if (! $coupon->appliesToAllBranches($services->pluck('branch_id'))) {
                return ['error' => __('messages.invalid_coupon')];
            }

            $couponDiscount = $coupon->discount_type === 'percent'
                ? ($grossTotal * $coupon->discount_percentage) / 100
                : $coupon->discount_amount;
        }

        $couponDiscount = round(max(0, min($couponDiscount, $grossTotal)), 2);

        $grossAfterCoupon = max($grossTotal - $couponDiscount, 0);
        $paymentGatewayDiscount = min(
            FrontendPaymentSettings::paymentGatewayDiscountAmount($paymentMethod, $grossAfterCoupon),
            $grossAfterCoupon
        );
        $finalTotal = round(max($grossAfterCoupon - $paymentGatewayDiscount, 0), 2);
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
