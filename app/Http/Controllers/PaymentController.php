<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Product\Models\Cart;
use App\Models\GiftCard;
use Modules\Wallet\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Models\Setting;

class PaymentController extends Controller
{
    public function index(Request $request){
        $type_page = $request->has('ids') ? 'payment' : 'cart';
        $userId = auth()->user()->id;
        $cartproduct = [];
        $productPrice = 0;
        $productCount = 0;
        $GifttCount = 0;
        $GiftPrice = 0;
        
        
        
        
        if($type_page == 'payment'){
            $cartservice = Booking::with([
                    'service.service',
                    'service.employee',
                    'branch:id,name,description'
                ])
                ->where('created_by', $userId)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->where('payment_type', 'payment')
                ->where('payment_status', 0)
                ->whereNull('deleted_by')
                ->get();
        }else{
            $cartservice = Booking::with([
                    'service.service',
                    'service.employee',
                    'branch:id,name,description'
                ])
                ->where('created_by', $userId)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->where('payment_type', 'cart')
                ->where('payment_status', 0)
                ->whereNull('deleted_by')
                ->get();
            $cartproduct = Cart::with('product')->where(['user_id' => $userId])->get();
            $productPrice = $cartproduct->sum(function ($item) {
                $price = $item->product->max_price ?? $item->product->min_price ?? 0;
            
                return $price * ($item->qty ?? 1);
            });
            $productCount = $cartproduct->count();
            
            $gifts = GiftCard::where('user_id', auth()->id())->where('payment_status', 0 )->get();
            $GifttCount = $gifts->count();
            $GiftPrice = $gifts->sum(fn($g) => $g->subtotal ?? 0);
        }

        $servicePrice = $cartservice->sum(function ($item) {
            return $item->service ? ($item->service->service_price ?? 0) : 0;
        });
        
        $cartTotal = $servicePrice + $productPrice  + $GiftPrice;
        
        $discountTotal = $cartservice->sum(fn($item) =>
            $item->services->sum(fn($s) => $s->discount_amount ?? 0)
        );

    
        $finalPrice = $cartTotal - $discountTotal;
        
        $serviceCount = $cartservice->sum(fn($item) => $item->service ? 1 : 0);
        
        $wallet =  Wallet::where('user_id',$userId)->where('status', 1)->first();
        
        $ratePerPoint = Setting::get('point_value') ?? 0.5;
        $loyalty = LoyaltyPoint::where('user_id' ,$userId)->first();

        $currentPoints = $loyalty ? $loyalty->points : 0;
        $loyaltyBalance = $currentPoints * $ratePerPoint;
        
        $branches = $cartservice->map(function($item) {
            return [
                'branch_id' => $item->branch_id,
                'branch_name' => $item->branch?->name ?? 'غير محدد',
                'branch_description' => $item->branch?->description ?? '',
            ];
        })->unique('branch_id')->values();   

        

        return view('frontend::payment', compact('cartservice' , 'cartproduct' , 'finalPrice' , 'discountTotal' , 'serviceCount' , 'productCount' , 'productPrice' , 'GifttCount' , 'wallet' , 'loyaltyBalance' , 'branches'));
    }
}
