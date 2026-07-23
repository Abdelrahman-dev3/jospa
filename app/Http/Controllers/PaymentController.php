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
use App\Services\Payment\Strategies\UrPayPaymentStrategy;
use App\Support\FrontendPaymentSettings;

class PaymentController extends Controller
{
    public function index(Request $request){
        
        $isPayNow = $request->has('ids') ? 'payment' : 'cart';
        
        $userId = auth()->user()->id;
        
        $productPrice = $productCount = $GifttCount = $GiftPrice = 0;
        
        $cartproduct = collect();
        $gifts = collect();
        
        if($isPayNow == 'payment'){
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

        $hasPayableItems = $isPayNow === 'payment'
            ? $cartservice->isNotEmpty()
            : $cartservice->isNotEmpty() || $cartproduct->isNotEmpty() || $gifts->isNotEmpty();

        if (! $hasPayableItems) {
            return redirect()->route('cart.page')->with('error', __('messages.cart_empty'));
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
        $gatewayDiscounts = FrontendPaymentSettings::gatewayDiscounts();
        $tapPaymentSources = FrontendPaymentSettings::tapPaymentSources();
        $defaultPaymentMethod = FrontendPaymentSettings::defaultPaymentMethod($paymentMethods);
        $defaultPaymentSource = FrontendPaymentSettings::defaultTapPaymentSource($tapPaymentSources);

        return view('frontend::payment', compact('cartservice' , 'cartproduct' , 'finalPrice' , 'discountTotal' , 'serviceCount' , 'productCount' , 'productPrice' , 'GifttCount' , 'wallet' , 'loyaltyBalance' , 'branches', 'paymentMethods', 'gatewayDiscounts', 'tapPaymentSources', 'defaultPaymentMethod', 'defaultPaymentSource'));
    }

    public function payment(Request $request)
    {
        $method = $request->get('paymentMethod');
        $isPayNow = $request->ids ? 'payment' : 'cart';

        if ($this->isComingSoonPaymentChoice($request)) {
            $message = $this->comingSoonPaymentMessage();
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => $message], 422);
            }

            throw ValidationException::withMessages([
                'paymentMethod' => $message,
            ]);
        }

        if (! $this->isPaymentMethodEnabled($method, $request)) {
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
            'urpay', 'stcpay', 'applepay_urpay' => app(UrPayPaymentStrategy::class),
            'tabby' => app(TabbyPaymentStrategy::class),
            'tamara' => app(TamaraPaymentStrategy::class),
            default => throw ValidationException::withMessages([
                'paymentMethod' => __('messages.invalid_payment_method'),
            ]),
        };

        return $strategy->pay($request, $isPayNow);
    }

    public function tabbySuccess(Request $request, $invoice = null)
    {
        return app(TabbyPaymentStrategy::class)->callback($request);
    }

    public function tabbyFail(Request $request, $invoice = null)
    {
        return app(TabbyPaymentStrategy::class)->fail($request, $invoice);
    }

    public function tabbyCancel(Request $request, $invoice = null)
    {
        return app(TabbyPaymentStrategy::class)->cancel($request, $invoice);
    }

    public function urpaySuccess(Request $request)
    {
        return app(UrPayPaymentStrategy::class)->success($request);
    }

    public function urpayFail(Request $request)
    {
        return app(UrPayPaymentStrategy::class)->failure($request);
    }

    public function urpayCancel(Request $request)
    {
        return app(UrPayPaymentStrategy::class)->cancel($request);
    }

    private function isPaymentMethodEnabled(?string $method, Request $request): bool
    {
        if (! $method) {
            return false;
        }

        $checkMethod = in_array($method, ['stcpay', 'applepay_urpay'], true) ? 'urpay' : $method;

        if ((FrontendPaymentSettings::paymentMethods()[$checkMethod] ?? 0) !== 1) {
            return false;
        }

        if ($method !== 'card') {
            return true;
        }

        $brand = strtoupper((string) $request->get('brand', 'VISA'));
        $source = match ($brand) {
            'MADA' => 'src_sa.mada',
            'APPLEPAY', 'APPLE_PAY', 'APPLE' => 'src_apple_pay',
            default => 'src_card',
        };

        return FrontendPaymentSettings::isEnabledTapSource($source);
    }

    private function isComingSoonPaymentChoice(Request $request): bool
    {
        return in_array((string) $request->get('paymentMethod', ''), [
            'tabby',
            'tamara',
        ], true);
    }

    private function comingSoonPaymentMessage(): string
    {
        return app()->getLocale() === 'ar'
            ? 'وسيلة الدفع هذه ستكون متاحة قريبًا.'
            : 'This payment method will be available soon.';
    }
}
