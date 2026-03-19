<?php

namespace App\Http\Controllers;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Wallet\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Services\TaqnyatSmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Booking\Models\BookingTransaction;
use Carbon\Carbon;
use App\Models\GiftCard;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;
use Modules\Product\Models\Cart;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use App\Services\TapPaymentService;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\Setting;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\UserCouponRedeem;



class BookingCartController extends Controller
{


    public function index(Request $request)
    {
        $userId = auth()->user()->id;
        
        $services = Booking::getUserIncompleteBookings($userId, 'cart', ['service.service', 'service.employee']);

        $servicePrice = $services->sum(function ($item) {
            return $item->service ? ($item->service->service_price ?? 0) : 0;
        });
        
        $products = Cart::with('product')->where(['user_id' => $userId])->get();
        
        $productPrice = $products->sum(function ($item) {
            $price = $item->product->max_price ?? $item->product->min_price ?? 0;
            return $price * ($item->qty ?? 1);
        });
        
        $gifts = GiftCard::where('user_id', $userId)->where('payment_status', 0 )->get();

        $GiftPrice = $gifts->sum(fn($g) => $g->subtotal ?? 0);
        
        $cartTotal = $servicePrice + $productPrice + $GiftPrice;
        
        $discountTotal = $services->sum(fn($item) =>
            $item->services->sum(fn($s) => $s->discount_amount ?? 0)
        );


        $finalPrice = $cartTotal - $discountTotal;
        
        // counting services and products
        $serviceCount = $services->sum(fn($item) => $item->service ? 1 : 0);
        $productCount = $products->count();

        return view('frontend.cart.index', compact('services' , 'products' , 'finalPrice' , 'discountTotal' , 'serviceCount' , 'productCount', 'gifts'));
    }

     public function store(Request $request)
    {
        $user = auth()->user();
        $data = $request->all();
        $btn_value = $request->btn_value;
        if (!$user) {
            session()->put('temp_booking', [
                'data' => $data,
                'btn_value' => $btn_value,
                'created_at' => now(),
            ]);
            return response()->json([
                'success' => false,
                'need_login' => true,
                'message' => 'يرجى تسجيل الدخول لإكمال الحجز.'
            ], 200);
        }
        $pendingSlots = [];
        foreach ($data['services'] ?? [] as $service) {
            foreach ($service['subServices'] ?? [] as $sub) {
                $slotData = $this->prepareSlotData($sub);
                if (!$slotData) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.invalid_data')
                    ], 422);
                }

                if ($this->hasSlotConflict($slotData['staff_id'], $slotData['start_date_time'], $slotData['duration'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('branch.branch_reserved')
                    ], 409);
                }

                if ($this->hasPendingSlotConflict($pendingSlots, $slotData['staff_id'], $slotData['start_date_time'], $slotData['duration'])) {
                    return response()->json([
                        'success' => false,
                        'message' => __('branch.branch_reserved')
                    ], 409);
                }

                $pendingSlots[] = [
                    'staff_id' => $slotData['staff_id'],
                    'start_date_time' => $slotData['start_date_time']->copy(),
                    'duration' => $slotData['duration'],
                ];
            }
        }

        DB::beginTransaction();
        try {
            if (!empty($data['services'])) {
                foreach ($data['services'] as $service) {
                    if (!empty($service['subServices'])) {
                        foreach ($service['subServices'] as $sub) {
                            $subId = $sub['id'];
                            $date = $sub['date'];
                            $time = $sub['time'];
                            $duration = $sub['duration'];
                            $price = $sub['price'];
                            $staffId = $sub['staffId'];
                            $startDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
                            
                            $booking = new Booking();
                            if($data['branch'] != 0){
                                $booking->note = 'العميل: ' . $user->first_name .
                                    '، الجوال: ' .  $user->mobile .
                                    '، الخدمة: ' . $subId;
                            }else{
                                $booking->note =  'اسم العميل ' . $data['customerName'] . 'رقم العميل ' . $data['mobileNo'] . 'الحي ' . $data['neighborhood'] ;
                                $booking->location       =  $data['locationInput'];
                            }
                            $booking->start_date_time = $startDateTime;
                            $booking->user_id         = $user->id;
                            $booking->branch_id       = $data['branch'] ?? 1;
                            $booking->created_by      = $user->id;
                            $booking->status          = 'pending';
                            $booking->payment_type       =  $btn_value;
                            $booking->save();
                            
                            //  الحجز التاني
                            $bookingService = new BookingService();
                            $bookingService->booking_id       = $booking->id;
                            $bookingService->service_id       = $subId;
                            $bookingService->employee_id      = $staffId;
                            $bookingService->start_date_time  = $startDateTime;
                            $bookingService->service_price    = \Modules\Service\Models\Service::find($subId)->default_price ?? 0;
                            $bookingService->duration_min     = $duration;
                            $bookingService->sequance         = 1;
                            $bookingService->created_by      = $user->id;
                            $bookingService->save();

                            $loyalty = \App\Models\LoyaltyPoint::firstOrCreate(
                                ['user_id' => $user->id],
                                ['points' => 0]
                            );
                        }
                    }
                }
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => __('messages.booking_added_to_cart')
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    private function prepareSlotData(array $sub): ?array
    {
        $serviceId = (int) ($sub['id'] ?? 0);
        $staffId = (int) ($sub['staffId'] ?? 0);
        $date = $sub['date'] ?? null;
        $time = $sub['time'] ?? null;
        $duration = max(1, (int) ($sub['duration'] ?? 0));

        if (!$serviceId || !$staffId || !$date || !$time) {
            return null;
        }

        try {
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        } catch (\Throwable $e) {
            return null;
        }

        return [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'start_date_time' => $startDateTime,
            'duration' => $duration,
        ];
    }

    private function hasSlotConflict(int $staffId, Carbon $startDateTime, int $duration): bool
    {
        $requestedEnd = $startDateTime->copy()->addMinutes(max(1, $duration));

        $existingSlots = BookingService::where('employee_id', $staffId)
            ->whereDate('start_date_time', $startDateTime->toDateString())
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', ['pending', 'confirmed', 'check_in']);
            })
            ->get(['start_date_time', 'duration_min']);

        foreach ($existingSlots as $slot) {
            $existingStart = Carbon::parse($slot->start_date_time);
            $existingDuration = max(1, (int) ($slot->duration_min ?? 0));
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            if ($existingStart->lt($requestedEnd) && $existingEnd->gt($startDateTime)) {
                return true;
            }
        }

        return false;
    }

    private function hasPendingSlotConflict(array $pendingSlots, int $staffId, Carbon $startDateTime, int $duration): bool
    {
        $requestedEnd = $startDateTime->copy()->addMinutes(max(1, $duration));

        foreach ($pendingSlots as $slot) {
            if (($slot['staff_id'] ?? 0) !== $staffId) {
                continue;
            }

            $existingStart = $slot['start_date_time'];
            $existingEnd = $existingStart->copy()->addMinutes(max(1, (int) ($slot['duration'] ?? 0)));

            if ($existingStart->lt($requestedEnd) && $existingEnd->gt($startDateTime)) {
                return true;
            }
        }

        return false;
    }
    
    
    public function destroy($id)
    {
        $booking = Booking::find($id);
    
        if (!$booking) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }
        $booking->bookingService()->delete();
        $booking->delete();
    
        return redirect()->back()->with('success', __('messages.item_removed_from_cart'));
    } 

    public function destroy_product($id)
    {
        $product = Cart::findOrFail($id);
    
        if (!$product) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }
        
        $product->delete();
    
        return redirect()->back()->with('success', __('messages.item_removed_from_cart'));
    } 

    public function destroy_gift($id)
    {
        $gift = GiftCard::findOrFail($id);
    
        if (!$gift) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }
        
        $gift->delete();
    
        return redirect()->back()->with('success', __('messages.item_removed_from_cart'));
    } 

    
    public function destroy_All()
    {
        $user = auth()->user();
        
        $bookings = Booking::with('services')
            ->where('created_by', $user->id)
            ->unpaid()
            ->get();
        
        foreach ($bookings as $booking) {
            $booking->bookingService()->delete();
            $booking->delete();
        }

        Cart::where('user_id', $user->id)->delete();
        
        GiftCard::where('user_id', $user->id)->where('payment_status', 0)->delete();

        return redirect()->back()->with('success', __('messages.items_removed_from_cart'));
    }

    
     public function balance(Request $request)
    {
        $user = $request->user(); // المستخدم الحالي من التوكن

        $points = DB::table('loyalty_points')
                    ->where('user_id', $user->id)
                    ->sum('points'); // لو في أكثر من سجل، نجمع النقاط كلها

        return response()->json([
            'user_id' => $user->id,
            'loyalty_points' => $points,
        ]);
    }

    public function walletLoyaltyBalance(Request $request)
    {
        $user = $request->user();

        $wallet = Wallet::where('user_id', $user->id)->where('status', 1)->first();
        $walletBalance = $wallet ? (float) $wallet->amount : 0.0;

        $points = (int) (LoyaltyPoint::where('user_id', $user->id)->value('points') ?? 0);
        $ratePerPoint = (float) (Setting::get('point_value') ?? 0.5);
        $loyaltyBalance = $points * $ratePerPoint;

        return response()->json([
            'user_id' => $user->id,
            'wallet_balance' => $walletBalance,
            'loyalty_points' => $points,
            'loyalty_balance' => $loyaltyBalance,
            'loyalty_rate' => $ratePerPoint,
        ]);
    }


    public function handlePaymentResult(Request $request)
    {
        $tapId = $request->get('tap_id');

        if (!$tapId) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'No tap_id provided.'], 400);
            }
            return view('frontend.payment-status.erpay')->with('error', 'No tap_id provided.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TAP_SECRET_KEY'),
        ])->get("https://api.tap.company/v2/charges/{$tapId}");

        $charge = $response->json();

        if (!isset($charge['status']) || $charge['status'] !== 'CAPTURED') {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment failed or not captured.',
                    'tap_response' => $charge
                ]);
            }

            return view('frontend.payment-status.failed');
        }

        $user = $this->resolvePaymentUser($request);
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'User not authenticated.'], 401);
            }
            return view('frontend.payment-status.failed')->with('error', 'User not authenticated.');
        }

        if ($this->hasLegacyPaymentSession()) {
            $discountAmount = session('discountAmount', 0);
            $loyaltyDiscount = session('loyaltyDiscount', 0);
            $finalTotal = session('finalTotal', 0);

            $cartIds = Booking::where('user_id', $user->id)
                ->unpaid()
                ->pluck('id')
                ->toArray();

            $gift_ids = GiftCard::where('user_id', $user->id)
                ->where('payment_status', 0)
                ->pluck('id')
                ->toArray();

            if ($loyaltyDiscount > 0) {
                DB::table('loyalty_points')
                    ->where('user_id', $user->id)
                    ->where('points', '>=', $loyaltyDiscount)
                    ->decrement('points', $loyaltyDiscount);
            }

            $this->addLoyaltyPoints($user->id, $charge['amount']);
            $couponCode = $request->get('coupon_code') ?? $request->get('invoiceCopon');
            $giftCode = $request->get('gift_code');
            $invoiceId = $this->storeInvoice($user->id, $discountAmount, $loyaltyDiscount, $finalTotal, $cartIds, $gift_ids, $couponCode, $giftCode);
            $this->paymentSuccess($cartIds, 'card', $tapId, $invoiceId);

            $this->activateGiftCards($user->id);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Payment successful.',
                    'data' => $charge
                ]);
            }

            return view('frontend.payment-status.captured');
        }

        $couponCode = $request->get('coupon_code') ?? $request->get('invoiceCopon');
        $paymentMethod = $this->getRequestedPaymentMethod($request);
        $clientDiscountRaw = $request->get('discount_amount', $request->get('discountAmount'));
        $clientDiscount = null;
        if ($clientDiscountRaw !== null && $clientDiscountRaw !== '') {
            if (!is_numeric($clientDiscountRaw)) {
                if ($request->expectsJson()) {
                    return response()->json(['status' => false, 'message' => 'Invalid discount amount.'], 422);
                }
                return view('frontend.payment-status.failed')->with('error', 'Invalid discount amount.');
            }
            $clientDiscount = (float) $clientDiscountRaw;
        }
        $calculator = app(PaymentCalculatorService::class);
        $totalData = $calculator->calculateTotal('cart', $couponCode);

        if (isset($totalData['error'])) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => $totalData['error']], 422);
            }
            return view('frontend.payment-status.failed')->with('error', $totalData['error']);
        }
        
        if ($clientDiscount !== null) {
            $expectedDiscount = (float) $totalData['discountAmount'];
            if (abs($clientDiscount - $expectedDiscount) > 0.01) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Discount amount mismatch.',
                        'expected' => $expectedDiscount,
                        'provided' => $clientDiscount,
                    ], 422);
                }
                return view('frontend.payment-status.failed')->with('error', 'Discount amount mismatch.');
            }
        }

        $subMethodService = app(PaymentSubMethodsService::class);
        $subResult = $subMethodService->apply($user->id, $request, $totalData['total']);

        if (isset($subResult['error'])) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => $subResult['error']], 422);
            }
            return view('frontend.payment-status.failed')->with('error', $subResult['error']);
        }

        $expectedCharge = (float) $subResult['remaining_amount'];
        $chargedAmount = (float) ($charge['amount'] ?? 0);

        if ($expectedCharge > 0 && $chargedAmount > 0 && abs($expectedCharge - $chargedAmount) > 0.01) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Paid amount does not match expected amount.',
                    'expected' => $expectedCharge,
                    'paid' => $chargedAmount,
                ], 422);
            }
            return view('frontend.payment-status.failed')->with('error', 'Paid amount does not match expected amount.');
        }

        $finalizer = app(PaymentFinalizerService::class);
        $subPayments = array_merge($subResult ?? [], [
            'gift_code' => $request->get('gift_code'),
        ]);
        $finalizer->finalizePayment(
            $user->id,
            $totalData['total'],
            $totalData['tax'],
            $totalData['discountAmount'],
            'cart',
            $totalData['cart_ids'] ?? [],
            $totalData['gift_ids'] ?? [],
            $paymentMethod,
            $couponCode ?? '',
            true,
            $subPayments
        );
        $subMethodService->apply($user->id, $request, $totalData['total'], true);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'Payment successful.',
                'data' => $charge
            ]);
        }

        return view('frontend.payment-status.captured');
}

    public function cartPay(Request $request)
    {
        $user = $request->user() ?? auth()->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not authenticated.'], 401);
        }

        $couponCode = $request->get('coupon_code') ?? $request->get('invoiceCopon');
        $paymentMethod = $this->getRequestedPaymentMethod($request);
        if ($paymentMethod === 'card' && (int) Setting::get('tap_payment_method', 1) !== 1) {
            return response()->json(['status' => false, 'message' => 'Payment method not available.'], 422);
        }
        if ($paymentMethod !== 'card') {
            return app(\App\Http\Controllers\PaymentController::class)->payment($request);
        }
        $clientDiscountRaw = $request->get('discount_amount', $request->get('discountAmount'));
        $clientDiscount = null;
        if ($clientDiscountRaw !== null && $clientDiscountRaw !== '') {
            if (!is_numeric($clientDiscountRaw)) {
                return response()->json(['status' => false, 'message' => 'Invalid discount amount.'], 422);
            }
            $clientDiscount = (float) $clientDiscountRaw;
        }
        $calculator = app(PaymentCalculatorService::class);
        $totalData = $calculator->calculateTotal('cart', $couponCode);

        if (isset($totalData['error'])) {
            return response()->json(['status' => false, 'message' => $totalData['error']], 422);
        }
        
        if ($clientDiscount !== null) {
            $expectedDiscount = (float) $totalData['discountAmount'];
            if (abs($clientDiscount - $expectedDiscount) > 0.01) {
                return response()->json([
                    'status' => false,
                    'message' => 'Discount amount mismatch.',
                    'expected' => $expectedDiscount,
                    'provided' => $clientDiscount,
                ], 422);
            }
        }

        $subMethodService = app(PaymentSubMethodsService::class);
        $subResult = $subMethodService->apply($user->id, $request, $totalData['total']);

        if (isset($subResult['error'])) {
            return response()->json(['status' => false, 'message' => $subResult['error']], 422);
        }

        $remainingAmount = (float) $subResult['remaining_amount'];

        if ($remainingAmount <= 0) {
            $finalizer = app(PaymentFinalizerService::class);
            $subPayments = array_merge($subResult ?? [], [
                'gift_code' => $request->get('gift_code'),
            ]);
            $finalizer->finalizePayment(
                $user->id,
                $totalData['total'],
                $totalData['tax'],
                $totalData['discountAmount'],
                'cart',
                $totalData['cart_ids'] ?? [],
                $totalData['gift_ids'] ?? [],
                'sub_methods',
                $couponCode ?? '',
                true,
                $subPayments
            );
            $subMethodService->apply($user->id, $request, $totalData['total'], true);

            return response()->json([
                'status' => true,
                'message' => 'Payment completed using sub methods.',
                'data' => [
                    'paid' => true,
                    'amount' => $totalData['total'],
                ],
            ]);
        }

        $tap = new TapPaymentService();
        $paymentSource = $request->get('payment_source') ?? 'src_card';

        $redirectUrl = $this->buildCartPaymentRedirectUrl($request, $user->id, $couponCode);

        $charge = $tap->createCharge(
            $remainingAmount,
            [
                "name"         => $user->first_name . $user->last_name,
                "country_code" => "966",
                "phone"        => $user->mobile,
                "method"       => $paymentSource,
            ],
            $redirectUrl
        );

        if (!isset($charge['transaction']['url'])) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create payment charge.',
                'data' => $charge,
            ], 502);
        }

        $paymentUrl = $this->applyTapLanguage($charge['transaction']['url']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => 'Redirect to payment gateway.',
                'data' => [
                    'payment_url' => $paymentUrl,
                    'charge_id' => $charge['id'] ?? null,
                    'amount' => $remainingAmount,
                    'payment_method' => $paymentMethod,
                    'discount_amount' => $totalData['discountAmount'] ?? 0,
                ],
            ]);
        }

        return redirect()->away($paymentUrl);
    }

    private function buildCartPaymentRedirectUrl(Request $request, int $userId, ?string $couponCode): string
    {
        $params = array_filter([
            'user_id' => $userId,
            'coupon_code' => $couponCode,
            'wallet' => $request->boolean('wallet') ? 1 : null,
            'loyalty' => $request->boolean('loyalty') ? 1 : null,
            'gift_code' => $request->get('gift_code'),
            'payment_method' => $this->getRequestedPaymentMethod($request),
            'discount_amount' => $request->get('discount_amount', $request->get('discountAmount')),
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        if ($request->expectsJson()) {
            return URL::temporarySignedRoute(
                'api.cart.payment.success',
                now()->addMinutes(30),
                $params
            );
        }

        $baseUrl = url('/success-py-invoice');
        return $params ? $baseUrl . '?' . http_build_query($params) : $baseUrl;
    }

    private function applyTapLanguage(string $paymentUrl): string
    {
        $lang = app()->getLocale() ?? 'en';
        $paymentUrl = preg_replace('/([&?])language=[^&]+/', '$1language=' . $lang, $paymentUrl);

        if (!str_contains($paymentUrl, 'language=')) {
            $paymentUrl .= (str_contains($paymentUrl, '?') ? '&' : '?') . 'language=' . $lang;
        }

        return $paymentUrl;
    }

    private function resolvePaymentUser(Request $request): ?User
    {
        $user = $request->user() ?? auth()->user();
        if ($user) {
            return $user;
        }

        if (! $request->hasValidSignatureWhileIgnoring(['tap_id', 'payment_id', 'checkout_id', 'status', 'payment_method'])) {
            return null;
        }

        $userId = (int) $request->get('user_id');
        if ($userId <= 0) {
            return null;
        }

        return User::find($userId);
    }

    private function hasLegacyPaymentSession(): bool
    {
        return session()->has('finalTotal')
            || session()->has('discountAmount')
            || session()->has('loyaltyDiscount');
    }

    public function addLoyaltyPoints($userId, $paidAmount)
    {
        $pointsToAdd = floor($paidAmount / 100) * 5;

        if ($pointsToAdd <= 0) {
            return;
        }

        $loyalty = LoyaltyPoint::firstOrNew(['user_id' => $userId]);
        $loyalty->points = ($loyalty->points ?? 0) + $pointsToAdd;
        $loyalty->save();
    }

    private function storeInvoice($userId, $discountAmount, $loyaltyDiscount, $finalTotal, $cartIds , $gift_ids = null, $couponCode = null, $giftCode = null)
    {
        $invoice = Invoice::create([
            'user_id' => $userId,
            'cart_ids' => json_encode($cartIds),
            'gift_ids' => json_encode($gift_ids),
            'coupon_code' => $couponCode ?: null,
            'gift_code' => $giftCode ?: null,
            'discount_amount' => $discountAmount,
            'loyalty_points_discount' => $loyaltyDiscount,
            'final_total' => $finalTotal,
        ]);

        $this->syncCouponRedeemInvoice((int) $userId,(string) ($couponCode ?? ''),(float) $discountAmount,(array) $cartIds,(int) $invoice->id);

        return $invoice->id;
    }

    private function syncCouponRedeemInvoice(int $userId, string $couponCode, float $discountAmount, array $cartIds, int $invoiceId): void
    {
        if ($couponCode === '' || $discountAmount <= 0 || $invoiceId <= 0) {
            return;
        }

        $coupon = Coupon::where('coupon_code', $couponCode)->first();
        if (! $coupon) {
            return;
        }

        $updatedRows = 0;

        if (! empty($cartIds)) {
            $updatedRows = UserCouponRedeem::query()->where('user_id', $userId)->where('coupon_id', $coupon->id)->whereIn('booking_id', $cartIds)->whereNull('invoice_id')->update(['invoice_id' => $invoiceId,]);
        }

        if ($updatedRows > 0) {
            return;
        }

        UserCouponRedeem::create([
            'user_id' => $userId,
            'coupon_code' => $coupon->coupon_code,
            'discount' => $discountAmount,
            'coupon_id' => $coupon->id,
            'invoice_id' => $invoiceId,
        ]);
    }

    public function checkLoyaltyPoints(Request $request)
    {
        $user = auth()->user();
        $points = LoyaltyPoint::where('user_id', $user->id)->value('points') ?? 0;

        return response()->json([
            'points' => $points,
        ]);
    }

    private function paymentSuccess(array $cartIds, $paymentMethod, $tapId = null, $invoiceId = null): void
    {
        foreach ($cartIds as $bookingId) {
            BookingTransaction::create([
                'booking_id'     => $bookingId,
                'invoice_id' => $invoiceId,
                'external_transaction_id' => $tapId,
                'transaction_type' => $paymentMethod,
                'payment_status' => 1,
            ]);
        }
    }
    
    private function activateGiftCards($userId)
    {
        // sms
        $smsService = new TaqnyatSmsService();
        
        $giftCards = GiftCard::where('user_id', $userId)
            ->where('payment_status', 0)
            ->get();
    
        foreach ($giftCards as $giftCard) {
            $ref = null;
            $balance = 0;
    
            if (in_array($giftCard->delivery_method, ['electronic_card', 'email', 'بطاقة الكترونية'], true)) {
                $ref = 'REF-' . strtoupper(Str::random(8));
                $balance = $giftCard->subtotal;
            }
    
        $giftCard->update([
                'payment_status' => 1,
                'ref'            => $ref,
                'balance'        => $balance,
            ]);

        $phone = $giftCard->sender_phone;
    
        if ($phone) { $smsService->sendGift($phone, $giftCard->sender_name , 'sender');}
    
        $phone_2 = $giftCard->recipient_phone;
    
        if ($phone_2) {$smsService->sendGift($phone_2, $giftCard->recipient_name , 'recipient' , $ref);}
        }
    }
    
    public function addToCart(Request $request, $id)
    {
        $qty = (int) $request->input('qty', $request->query('qty', 1));

        if ($qty < 1) {
            return response()->json([
                'status' => 'failed',
                'message' => __('messages.cart_invalid_quantity'),
            ], 422);
        }

        $product = Product::findOrFail($id);
        $stock = (int) ($product->stock_qty ?? 0);

        if ($qty > $stock) {
            return response()->json([
                'status' => 'failed',
                'message' => __('messages.cart_quantity_unavailable'),
            ], 422);
        }

        $exist = Cart::where([
            'user_id' => auth()->id(),
            'product_id' => $id,
        ])->first();

        if ($exist) {
            $newQty = ((int) $exist->qty) + $qty;

            if ($newQty > $stock) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('messages.cart_quantity_unavailable'),
                ], 422);
            }

            $exist->update([
                'qty' => $newQty,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('messages.cart_product_quantity_increased'),
            ]);
        }

        Cart::create([
            'user_id' => auth()->id(),
            'location_id' => 1,
            'product_id' => $id,
            'product_variation_id' => null,
            'qty' => $qty,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('messages.cart_product_added'),
        ]);
    }

    private function getRequestedPaymentMethod(Request $request): string
    {
        $method = $request->get('payment_method') ?? $request->get('paymentMethod') ?? 'card';
        $method = is_string($method) ? trim($method) : '';
        return $method !== '' ? $method : 'card';
    }
}
