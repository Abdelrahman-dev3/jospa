<?php

namespace App\Http\Controllers;
use App\Models\BookingCart;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use Modules\Booking\Models\BookingProduct;
use Modules\Wallet\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Models\LoyaltyPointTransaction;
use App\Services\TaqnyatSmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Modules\Booking\Models\BookingTransaction;
use Carbon\Carbon;
use App\Models\GiftCard;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;
use Modules\Product\Models\Cart;



class BookingCartController extends Controller
{


    public function index(Request $request)
    {
        $userId = auth()->user()->id;
        
        $services = Booking::with('service.service','service.employee')->where('created_by', $userId)->whereNotIn('status', ['cancelled', 'completed'])->where('payment_type', 'cart')->where('payment_status', 0)->whereNull('deleted_by')->get();

        $servicePrice = $services->sum(function ($item) {
            return $item->service ? ($item->service->service_price ?? 0) : 0;
        });
        
        $products = Cart::with('product')->where(['user_id' => $userId])->get();
        
        $productPrice = $products->sum(function ($item) {
            $price = $item->product->max_price ?? $item->product->min_price ?? 0;
            return $price * ($item->qty ?? 1);
        });
        
        $gifts = GiftCard::where('user_id', auth()->id())->where('payment_status', 0 )->get();

        $GiftPrice = $gifts->sum(fn($g) => $g->subtotal ?? 0);
        
        $cartTotal = $servicePrice + $productPrice + $GiftPrice;
        
        $discountTotal = $services->sum(fn($item) =>
            $item->services->sum(fn($s) => $s->discount_amount ?? 0)
        );


        $finalPrice = $cartTotal - $discountTotal;
        
        $serviceCount = $services->sum(fn($item) => $item->service ? 1 : 0);

        $productCount = $products->count();

        return view('frontend.cart.index', compact('services' , 'products' , 'finalPrice' , 'discountTotal' , 'serviceCount' , 'productCount', 'gifts'));
    }

     public function store(Request $request)
    {
        $user = auth()->user();
        $data = $request->all();
        $btn_value = $request->btn_value;
        $branch = $data['branch'];
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
            return response()->json([
                'success' => true,
                'message' => __('messages.booking_added_to_cart')
            ], 201);
        }
    
    public function destroy($id)
    {
        $user = auth()->user();
    
        $booking = Booking::find($id);
    
        if (!$booking) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }
        
        $booking->delete();
    
        return redirect()->back()->with('success', __('messages.item_removed_from_cart'));
    } 
 
    public function destroy_product($id)
    {
        $user = auth()->user();
    
        $product = Cart::findOrFail($id);
    
        if (!$product) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }
        
        $product->delete();
    
        return redirect()->back()->with('success', __('messages.item_removed_from_cart'));
    } 

    public function destroy_gift($id)
    {
        $user = auth()->user();
    
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
        
        $bookings = Booking::with('services', 'products')->where('user_id', $user->id)->where('payment_status', 0)->get();
        
        foreach ($bookings as $booking) {
            $booking->services()->delete();
    
            $booking->products()->delete();
    
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
    
        if (isset($charge['status']) && $charge['status'] === 'CAPTURED') {
            $user = auth()->user();
    
            $discountAmount = session('discountAmount', 0);
            $loyaltyDiscount = session('loyaltyDiscount', 0);
            $totalDiscount = $discountAmount + $loyaltyDiscount;
            $finalTotal = session('finalTotal', 0);
    
            $cartIds = Booking::where('user_id', $user->id)
                    ->where('payment_status', 0)
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
            $this->storeInvoice($user->id, $discountAmount, $loyaltyDiscount, $finalTotal, $cartIds , $gift_ids);
            $this->paymentSuccess( $cartIds , $tapId , 'card');
    
            Booking::where('user_id', $user->id)
                ->where('payment_status', 0)
                ->update(['payment_status' => 1]);
            
            $this->activateGiftCards($user->id);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Payment successful.',
                    'data' => $charge
                ]);
            }
    
            return view('frontend.payment-status.captured');
        } else {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment failed or not captured.',
                    'tap_response' => $charge
                ]);
            }
    
            return view('frontend.payment-status.failed');
        }
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

    private function storeInvoice($userId, $discountAmount, $loyaltyDiscount, $finalTotal, $cartIds , $gift_ids = null)
    {
        Invoice::create([
            'user_id' => $userId,
            'cart_ids' => json_encode($cartIds),
            'gift_ids' => json_encode($gift_ids),
            'discount_amount' => $discountAmount,
            'loyalty_points_discount' => $loyaltyDiscount,
            'final_total' => $finalTotal,
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

    private function paymentSuccess( array $cartIds , $tapId = null , $paymentMethod): void
    {
        foreach ($cartIds as $bookingId) {
            BookingTransaction::create([
                'booking_id'     => $bookingId,
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
    
            if ($giftCard->delivery_method == 'بطاقة الكترونية') {
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
        $qty = (int) $request->query('qty', 1);

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
}
