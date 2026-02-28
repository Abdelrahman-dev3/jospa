<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingTransaction;
use App\Models\LoyaltyPointTransaction;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\UserCouponRedeem;
use Modules\Wallet\Models\Wallet;
use App\Models\LoyaltyPoint;
use Modules\Product\Models\Cart;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderGroup;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\Product;
use App\Models\GiftCard;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Illuminate\Support\Str;

class PaymentFinalizerService
{
    /**
     * Finalize payment: save invoice, transactions, loyalty points, update orders and carts
     *
     * @param int $userId
     * @param float $paidAmount
     * @param float $discountAmount
     * @param array $cartIds
     * @param array $giftIds
     * @param bool $submethodsApplied
     * @return int $invoiceId
     */
    public function finalizePayment(
        int $userId,
        float $paidAmount,
        float $tax,
        float $discountAmount,
        string $pageType,
        array $cartIds = [],
        array $giftIds = [],
        string $paymentMethod ,
        string $couponCode ,
        bool $submethodsApplied = false,
        array $subPayments = []
    ): int {
        DB::transaction(function () use ($userId, $paidAmount,$tax, $discountAmount, $pageType, $cartIds, $giftIds, $submethodsApplied, &$invoiceId , $paymentMethod , $couponCode, $subPayments) {
            $product_ids = [];
            
            if($pageType == 'cart'){
                //  Convert Cart to Orders (if any)
                $orderData = $this->convertCartToOrders($userId);
                
                if (isset($orderData['error'])) {
                    throw new \Exception($orderData['error']);
                }
                    
                $product_ids = $orderData['order_group_ids'];
            }
            //️ Add Loyalty Points
            $this->addLoyaltyPoints($userId, $paidAmount);


            // Create Invoice
            $invoiceId = $this->storeInvoice($userId, $discountAmount,$tax ,$paidAmount, $cartIds , $giftIds , $product_ids , $couponCode, $subPayments);

            //  Create Booking Transactions
            $this->createTransactions( $cartIds ,  'INV-' . $invoiceId, $paymentMethod ?? 'Sub Methods');

            //  Update payment status
            Booking::whereIn('id', $cartIds)->update(['payment_status' => 1]);
            if (!empty($giftIds)) {
                GiftCard::whereIn('id', $giftIds)->update(['payment_status' => 1]);
                
                $giftCards = GiftCard::whereIn('id', $giftIds)->whereIn('delivery_method', ['electronic_card', 'email', 'بطاقة الكترونية'])->get();
            
                foreach ($giftCards as $gift) {
                    $gift->update([
                        'ref' => 'GC-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6)),
                        'balance' => $gift->subtotal,
                    ]);
                }
            }
        });

        return $invoiceId;
    }

    /**
     * Add loyalty points to user account
     */
    private function addLoyaltyPoints(int $userId, float $paidAmount): void
    {
        $pointsPer100 = Setting::get('points_per_100') ?? 5;
        $pointsToAdd = floor($paidAmount / 100) * $pointsPer100;

        if ($pointsToAdd <= 0) return;

        $loyalty = LoyaltyPoint::firstOrNew(['user_id' => $userId]);
        $loyalty->points = ($loyalty->points ?? 0) + $pointsToAdd;
        $loyalty->save();
        
        LoyaltyPointTransaction::create([
            'user_id' => $userId,
            'action' => 'add',
            'points' => $pointsToAdd,
            'balance_after' => $loyalty->points,
            'source' => 'اضافة نقاط ولاء بناءا علي المبلغ الاجمالي :' . $paidAmount ,
        ]);
    }

    /**
     * Store invoice
     */
    private function storeInvoice(int $userId, float $discountAmount,float $tax , float $finalTotal, array $cartIds, array $giftIds, array $product_ids , string $couponCode, array $subPayments = []): int
    {
        $giftCode = $subPayments['gift_code'] ?? null;
        $giftAmount = (float) ($subPayments['used_gift'] ?? 0);
        $invoice = Invoice::create([
            'user_id' => $userId,
            'cart_ids' => json_encode($cartIds),
            'gift_ids' => json_encode($giftIds),
            'product_ids' => $product_ids,
            'coupon_code' => $couponCode ?: null,
            'gift_code' => $giftCode ?: null,
            'gift_amount' => $giftAmount,
            'discount_amount' => $discountAmount,
            'taxs_service' => $tax,
            'loyalty_points_discount' => 0,
            'final_total' => $finalTotal,
        ]);
        $this->recordInvoiceCouponRedeem($userId, $couponCode, $discountAmount, $cartIds);
        return $invoice->id;
    }

    /**
     * Create Booking transactions
     */
    private function createTransactions(array $cartIds, string $transactionId, string $paymentMethod): void
    {
        foreach ($cartIds as $id) {
            BookingTransaction::create([
                'booking_id' => $id,
                'external_transaction_id' => $transactionId,
                'transaction_type' => $paymentMethod,
                'payment_status' => 1,
            ]);
        }
    }

    /**
     * Convert user's cart items to Orders & OrderItems
     */ 
     
     private function convertCartToOrders(int $userId): array
    {
        $carts = Cart::with('product')
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->get();

        if ($carts->isEmpty()) {
            return [
                'order_group_ids' => [],
                'order_ids' => [],
            ];
        }

        // Lock product rows to prevent concurrent checkout from overselling stock.
        $productIds = $carts->pluck('product_id')->unique()->values();
        $products = Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Check stock availability
        foreach ($carts as $cart) {
            $product = $products->get($cart->product_id);
            $stock = (int) ($product->stock_qty ?? 0);

            if ($cart->qty > $stock) {
                return [
                    'error' => __('messages.cart_product_out_of_stock', ['product' => $cart->product->name])
                ];
            }
        }
    
        /** ---------------- Order Group ---------------- */
        $orderGroup = new OrderGroup();
        $orderGroup->user_id = $userId;
        $orderGroup->phone_no = auth()->user()->mobile ?? null;
        $orderGroup->alternative_phone_no = null;
        $orderGroup->sub_total_amount = getSubTotal($carts, false, '', false);
        $orderGroup->total_tax_amount = 0;
        $orderGroup->type = 'online';
        $orderGroup->total_shipping_cost = 0;
        $orderGroup->total_tips_amount = 0;
        $orderGroup->payment_status = 'paid';
        $orderGroup->grand_total_amount =$orderGroup->sub_total_amount +$orderGroup->total_tax_amount +$orderGroup->total_tips_amount;
        $orderGroup->save();
    
        /** ---------------- Order ---------------- */
        $order = new Order();
        $order->order_group_id = $orderGroup->id;
        $order->user_id = $userId;
        $order->total_admin_earnings = $orderGroup->grand_total_amount;
        $order->shipping_cost = 0;
        $order->tips_amount = $orderGroup->total_tips_amount;
        $order->payment_status = 'paid';
        $order->save();
    
        /** ---------------- Order Items ---------------- */
        foreach ($carts as $cart) {
            $product = $products->get($cart->product_id);

            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->product_variation_id = 0;
            $item->product_id = $cart->product_id;
            $item->qty = $cart->qty;
            $item->unit_price = $product->min_price
                ?? $product->max_price
                ?? 0;
            $item->total_tax = 0;
            $item->total_price = $item->unit_price * $item->qty;
            $item->save();
    
            // Update product stats
            $product->total_sale_count += $item->qty;
            $product->stock_qty -= $item->qty;
            $product->save();
    
            // Remove cart
            $cart->delete();
        }
    
        return [
            'order_group_ids' => [$orderGroup->id],
            'order_ids'       => [$order->id],
        ];
    }
    
    
    private function recordInvoiceCouponRedeem($userId, $couponCode, $discountAmount, $cartIds): void
    {
        if (empty($couponCode) || $discountAmount <= 0) {
            return;
        }

        $coupon = Coupon::where('coupon_code', $couponCode)->first();
        if (! $coupon) {
            return;
        }
        
        $coupon->decrement('use_limit');
        
        UserCouponRedeem::create([
            'user_id' => $userId,
            'coupon_code' => $coupon->coupon_code,
            'discount' => $discountAmount,
            'coupon_id' => $coupon->id,
        ]);
    }
}
