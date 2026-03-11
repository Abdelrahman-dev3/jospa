<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Models\Booking;
use Modules\Product\Models\Cart;
use App\Models\GiftCard;
use Modules\Wallet\Models\Wallet;
use App\Models\LoyaltyPoint;
use App\Models\Setting;
use App\Services\Payment\Strategies\CardPaymentStrategy;
use App\Services\Payment\Strategies\TabbyPaymentStrategy;
use App\Services\Payment\Strategies\TamaraPaymentStrategy;
use App\Support\FrontendPaymentSettings;

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
            $cartservice = Booking::getUserIncompleteBookings($userId, 'payment', ['service.service','service.employee','branch:id,name,description',]);
        }else{
            $cartservice = Booking::getUserIncompleteBookings($userId, 'cart', ['service.service','service.employee','branch:id,name,description',]);
            $cartproduct = Cart::with('product')->where(['user_id' => $userId])->get();
            $productPrice = $cartproduct->sum(function ($item) {
                $price = $item->product->max_price ?? $item->product->min_price ?? 0;
            
                return $price * ($item->qty ?? 1);
            });
            $productCount = $cartproduct->count();
            
            $gifts = GiftCard::where('user_id', $userId)->where('payment_status', 0 )->get();
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

        $paymentMethods = FrontendPaymentSettings::paymentMethods();
        $tapPaymentSources = FrontendPaymentSettings::tapPaymentSources();
        $defaultPaymentMethod = FrontendPaymentSettings::defaultPaymentMethod($paymentMethods);
        $defaultPaymentSource = FrontendPaymentSettings::defaultTapPaymentSource($tapPaymentSources);

        return view('frontend::payment', compact('cartservice' , 'cartproduct' , 'finalPrice' , 'discountTotal' , 'serviceCount' , 'productCount' , 'productPrice' , 'GifttCount' , 'wallet' , 'loyaltyBalance' , 'branches', 'paymentMethods', 'tapPaymentSources', 'defaultPaymentMethod', 'defaultPaymentSource'));
    }

    public function payment(Request $request)
    {
        $method = $request->get('paymentMethod');
        $typePage = $request->ids ? 'payment' : 'cart';

        if (! $this->isPaymentMethodEnabled($method)) {
            $message = __('messages.invalid_payment_method');
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => $message], 422);
            }

            throw ValidationException::withMessages([
                'paymentMethod' => $message,
            ]);
        }

        $strategy = match ($method) {
            'card' => app(CardPaymentStrategy::class),
            'tabby' => app(TabbyPaymentStrategy::class),
            'tamara' => app(TamaraPaymentStrategy::class),
            default => throw ValidationException::withMessages([
                'paymentMethod' => __('messages.invalid_payment_method'),
            ]),
        };

        return $strategy->pay($request, $typePage);
    }

    public function tabbySuccess(Request $request, $invoice = null)
    {
        return app(TabbyPaymentStrategy::class)->callback($request);
    }

    public function tabbyFail($invoice = null)
    {
        session()->forget('tabby_payment');

        return view('frontend.payment-status.failed', ['message' => __('messages.payment_failed')]);
    }

    public function tabbyCancel($invoice = null)
    {
        session()->forget('tabby_payment');

        return view('frontend.payment-status.failed', ['message' => __('messages.payment_cancelled')]);
    }

    private function isPaymentMethodEnabled(?string $method): bool
    {
        if (! $method) {
            return false;
        }

        return (FrontendPaymentSettings::paymentMethods()[$method] ?? 0) === 1;
    }
}
