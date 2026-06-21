<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\User;
use App\Services\OdooBookingSyncService;
use App\Services\WhatsApp\PaidInvoiceWhatsAppService;
use Illuminate\Support\Collection;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingTransaction;
use Modules\Booking\Trait\BookingTrait;
use App\Models\LoyaltyPointTransaction;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\UserCouponRedeem;
use Modules\Wallet\Models\Wallet;
use App\Models\LoyaltyPoint;
use Modules\Package\Models\BookingPackages;
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
    use BookingTrait;

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
        array $cartIds,
        array $giftIds,
        string $paymentMethod ,
        string $couponCode ,
        bool $submethodsApplied = false,
        array $subPayments = []
    ): int {
        $invoiceId = 0;

        DB::transaction(function () use ($userId, $paidAmount,$tax, $discountAmount, $pageType, $cartIds, $giftIds, $submethodsApplied, &$invoiceId , $paymentMethod , $couponCode, $subPayments) {
            $product_ids = [];
            $bookingIdsToNotify = [];
            
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


            if ($pageType === 'cart') {
                $bookingIdsToNotify = Booking::whereIn('id', $cartIds)
                    ->unpaid()
                    ->pluck('id')
                    ->all();
            }

            // Create Invoice
            $invoiceId = $this->storeInvoice($userId, $discountAmount, $tax, $paidAmount, $cartIds, $giftIds, $product_ids, $couponCode, $paymentMethod ?? 'Sub Methods', $subPayments);

            //  Create Booking Transactions
            $this->createTransactions($cartIds, $invoiceId, 'INV-' . $invoiceId, $paymentMethod ?? 'Sub Methods');

            $this->createBookingCommissions($cartIds);
            $this->sendPaidCartBookingNotifications($bookingIdsToNotify);
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

        if ($invoiceId > 0 && $pageType === 'cart' && (! empty($cartIds) || ! empty($giftIds))) {
            app(OdooBookingSyncService::class)->syncPaidInvoice($invoiceId);
        }

        if ($invoiceId > 0) {
            try {
                app(PaidInvoiceWhatsAppService::class)->sendForInvoice($invoiceId);
            } catch (\Throwable $exception) {
                \Log::error('Failed to send paid invoice WhatsApp message.', [
                    'invoice_id' => $invoiceId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $invoiceId;
    }

    private function sendPaidCartBookingNotifications(array $bookingIds): void
    {
        if (empty($bookingIds)) {
            return;
        }

        $bookings = Booking::with([
            'branch.address.city_data',
            'branch.address.state_data',
            'branch.address.country_data',
            'branch.employee',
            'user',
            'services.employee',
            'products',
            'packages',
            'mainServices',
            'payment',
            'userCouponRedeem',
        ])->whereIn('id', $bookingIds)->get();

        foreach ($bookings as $booking) {
            try {
                $notifyMessage = str_replace(
                    '[[booking_id]]',
                    $booking->id,
                    'New booking #[[booking_id]] has been paid successfully.'
                );

                $this->sendNotificationOnBookingUpdate('new_booking', $notifyMessage, $booking);
            } catch (\Throwable $e) {
                \Log::error($e->getMessage());
            }
        }
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
    private function storeInvoice(int $userId, float $discountAmount, float $tax, float $finalTotal, array $cartIds, array $giftIds, array $product_ids, string $couponCode, string $paymentMethod, array $subPayments = []): int
    {
        $giftCode = $subPayments['gift_code'] ?? null;
        $giftAmount = (float) ($subPayments['used_gift'] ?? 0);
        $couponDiscountAmount = (float) ($subPayments['coupon_discount_amount'] ?? 0);
        $paymentGatewayDiscountAmount = (float) ($subPayments['payment_gateway_discount_amount'] ?? 0);
        $paymentGatewayDiscountMethod = $subPayments['payment_gateway_discount_method'] ?? null;
        $paymentGatewayDiscountLabel = $subPayments['payment_gateway_discount_label'] ?? null;
        $invoice = Invoice::create([
            'user_id' => $userId,
            'cart_ids' => $cartIds,
            'gift_ids' => $giftIds,
            'product_ids' => $product_ids,
            'coupon_code' => $couponCode ?: null,
            'gift_code' => $giftCode ?: null,
            'gift_amount' => $giftAmount,
            'payment_method' => $paymentMethod ?: null,
            'discount_amount' => $discountAmount,
            'coupon_discount_amount' => $couponDiscountAmount,
            'payment_gateway_discount_amount' => $paymentGatewayDiscountAmount,
            'payment_gateway_discount_method' => $paymentGatewayDiscountMethod,
            'payment_gateway_discount_label' => $paymentGatewayDiscountLabel,
            'taxs_service' => $tax,
            'loyalty_points_discount' => 0,
            'final_total' => $finalTotal,
        ]);
        $this->recordInvoiceCouponRedeem($invoice->id, $userId, $couponCode, $couponDiscountAmount, $cartIds);
        return $invoice->id;
    }

    /**
     * Create Booking transactions
     */
    private function createTransactions(array $cartIds, int $invoiceId, string $transactionId, string $paymentMethod): void
    {
        foreach ($cartIds as $id) {
            BookingTransaction::create([
                'booking_id' => $id,
                'invoice_id' => $invoiceId,
                'external_transaction_id' => $transactionId,
                'transaction_type' => $paymentMethod,
                'payment_status' => 1,
            ]);
        }
    }

    private function createBookingCommissions(array $cartIds): void
    {
        if (empty($cartIds)) {
            return;
        }

        $bookings = Booking::with(['commission'])
            ->whereIn('id', $cartIds)
            ->get();

        if ($bookings->isEmpty()) {
            return;
        }

        $serviceRows = BookingService::whereIn('booking_id', $cartIds)
            ->get(['booking_id', 'employee_id', 'service_price'])
            ->groupBy('booking_id');

        $employeeIds = collect()
            ->merge($serviceRows->flatten(1)->pluck('employee_id'))
            ->filter()
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) {
            return;
        }

        $employees = User::role('employee')
            ->whereIn('id', $employeeIds)
            ->with('commissions.mainCommission')
            ->get()
            ->keyBy('id');

        foreach ($bookings as $booking) {
            if ($booking->commission) {
                continue;
            }

            $commissionData = $this->buildCommissionData(
                $serviceRows->get($booking->id, collect()),
                $employees
            );

            if ($commissionData === null) {
                continue;
            }

            $booking->commission()->create($commissionData);
        }
    }

    private function buildCommissionData(Collection $serviceRows, Collection $employees): ?array
    {
        $employeeId = (int) ($serviceRows->first()->employee_id ?? 0);

        if ($employeeId <= 0) {
            return null;
        }

        $employee = $employees->get($employeeId);

        if (! $employee) {
            return null;
        }

        $totalAmount = $serviceRows->sum('service_price');

        $commissionAmount = 0.0;
        $hasRules = false;

        foreach ($employee->commissions as $employeeCommission) {
            $rule = $employeeCommission->mainCommission;

            if (! $rule) {
                continue;
            }

            $hasRules = true;

            if ($rule->commission_type === 'fixed') {
                $commissionAmount += (float) $rule->commission_value;
                continue;
            }

            $commissionAmount += ((float) $rule->commission_value * $totalAmount) / 100;
        }

        if (! $hasRules || $commissionAmount <= 0) {
            return null;
        }

        return [
            'employee_id' => $employeeId,
            'commission_amount' => $commissionAmount,
            'commission_status' => 'unpaid',
            'payment_date' => null,
        ];
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
    
    
    private function recordInvoiceCouponRedeem(int $invoiceId, int $userId, string $couponCode, float $discountAmount, array $cartIds): void
    {
        if (empty($couponCode) || $discountAmount <= 0) {
            return;
        }

        $coupon = Coupon::where('coupon_code', $couponCode)->first();
        if (! $coupon) {
            return;
        }

        $updatedRows = 0;

        if (! empty($cartIds)) {
            $updatedRows = UserCouponRedeem::query()
                ->where('user_id', $userId)
                ->where('coupon_id', $coupon->id)
                ->whereIn('booking_id', $cartIds)
                ->whereNull('invoice_id')
                ->update([
                    'invoice_id' => $invoiceId,
                ]);
        }

        if ($updatedRows > 0) {
            $coupon->syncExpiredState();
            return;
        }

        UserCouponRedeem::create([
            'user_id' => $userId,
            'coupon_code' => $coupon->coupon_code,
            'discount' => $discountAmount,
            'coupon_id' => $coupon->id,
            'invoice_id' => $invoiceId,
        ]);

        $coupon->syncExpiredState();
    }
}
