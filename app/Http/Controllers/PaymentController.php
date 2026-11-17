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
    public function checkAttemptStatus($attempt_id)
    {
        $attempt = \App\Models\PaymentAttempt::find($attempt_id);

        if (!$attempt) {
            return response()->json(['status' => false, 'message' => 'Payment attempt not found.'], 404);
        }

        $userId = auth()->id();
        if ($userId && $attempt->user_id !== $userId) {
            return response()->json(['status' => false, 'message' => 'Unauthorized.'], 403);
        }

        [$isFinal, $stopPolling, $pollAfter] = $this->pollingHints($attempt);

        return response()->json([
            'status' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'payment_status' => $attempt->status,
                'invoice_id' => $attempt->invoice_id,
                'can_retry_payment' => \App\Services\Payment\PaymentAttemptCancelService::canRetry($attempt),
                // Polling guidance for the app: wait poll_after_seconds between calls,
                // and stop as soon as stop_polling is true.
                'is_final' => $isFinal,
                'stop_polling' => $stopPolling,
                'poll_after_seconds' => $pollAfter,
            ]
        ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Retry-After', (string) ($pollAfter ?? 0));
    }

    /** Statuses that will not change any more. */
    private const FINAL_ATTEMPT_STATUSES = [
        \App\Models\PaymentAttempt::STATUS_PAID,
        \App\Models\PaymentAttempt::STATUS_FAILED,
        \App\Models\PaymentAttempt::STATUS_CANCELLED,
    ];

    /** After this, the app stops polling; the scheduled reconcile jobs settle the attempt in the background. */
    private const MAX_POLL_MINUTES = 30;

    /**
     * Back-off for status polling: frequent right after the payment, then slower.
     *
     * @return array{0: bool, 1: bool, 2: ?int} [is_final, stop_polling, poll_after_seconds]
     */
    private function pollingHints(\App\Models\PaymentAttempt $attempt): array
    {
        if (in_array($attempt->status, self::FINAL_ATTEMPT_STATUSES, true)) {
            return [true, true, null];
        }

        $ageSeconds = $attempt->created_at ? $attempt->created_at->diffInSeconds(now()) : 0;

        if ($ageSeconds >= self::MAX_POLL_MINUTES * 60) {
            return [false, true, null];
        }

        $pollAfter = match (true) {
            $ageSeconds < 60 => 3,
            $ageSeconds < 300 => 5,
            default => 15,
        };

        return [false, false, $pollAfter];
    }
    /**
     * POST /api/payment-attempt/cancel/{attempt_id}
     * The app calls this when the user leaves the gateway page (back / closed WebView).
     */
    public function cancelAttempt(Request $request, $attempt_id)
    {
        $attempt = \App\Models\PaymentAttempt::where('id', (int) $attempt_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $attempt) {
            return response()->json(['status' => false, 'message' => 'Payment attempt not found.'], 404);
        }

        [$attempt, $result] = app(\App\Services\Payment\PaymentAttemptCancelService::class)->cancelByUser($attempt, $request);

        $data = [
            'attempt_id' => $attempt->id,
            'payment_status' => $attempt->status,
            'invoice_id' => $attempt->invoice_id,
            'can_retry_payment' => \App\Services\Payment\PaymentAttemptCancelService::canRetry($attempt),
        ];

        if ($result === \App\Services\Payment\PaymentAttemptCancelService::RESULT_IN_PROGRESS) {
            return response()->json([
                'status' => false,
                'error_code' => 'payment_in_progress',
                'message' => app()->getLocale() === 'ar'
                    ? 'عملية الدفع قيد المعالجة لدى البنك، لا يمكن إلغاؤها الآن.'
                    : 'The payment is being processed by the bank and cannot be cancelled now.',
                'data' => $data,
            ], 409);
        }

        return response()->json([
            'status' => true,
            'cancelled' => $result === \App\Services\Payment\PaymentAttemptCancelService::RESULT_CANCELLED,
            'data' => $data,
        ]);
    }

    /** 409 returned instead of starting a new payment. Not cached under the Idempotency-Key. */
    private function blockedPaymentResponse(Request $request, string $errorCode, ?\App\Models\PaymentAttempt $attempt)
    {
        $ar = app()->getLocale() === 'ar';
        $message = match ($errorCode) {
            'previous_payment_completed' => $ar
                ? 'تم دفع هذه العملية بالفعل. يرجى تحديث السلة.'
                : 'This payment was already completed. Please refresh your cart.',
            'payment_already_starting' => $ar
                ? 'جارٍ بدء عملية دفع أخرى، يرجى الانتظار.'
                : 'Another payment is being started. Please wait.',
            default => $ar
                ? 'عملية دفع سابقة قيد المعالجة لدى البنك. يرجى الانتظار قبل المحاولة مرة أخرى.'
                : 'A previous payment is still being processed by the bank. Please wait before trying again.',
        };

        if (! $request->expectsJson()) {
            throw ValidationException::withMessages(['paymentMethod' => $message]);
        }

        return response()->json([
            'status' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'data' => $attempt ? [
                'attempt_id' => $attempt->id,
                'payment_status' => $attempt->status,
                'invoice_id' => $attempt->invoice_id,
            ] : null,
        ], 409);
    }

    public function index(Request $request){
        
        $isPayNow = $request->has('ids') ? 'payment' : 'cart';
        
        $userId = auth()->user()->id;
        
        $productPrice = $productCount = $GifttCount = $GiftPrice = 0;
        
        $cartproduct = collect();
        $gifts = collect();
        
        if($isPayNow == 'payment'){
            $cartservice = Booking::getUserIncompleteBookings($userId, 'payment', ['services.service','services.employee','branch:id,name,description',]);
        }else{
            $cartservice = Booking::getUserIncompleteBookings($userId, 'cart', ['services.service','services.employee','branch:id,name,description',]);
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
            return $item->services->sum(fn($s) => $s->service_price ?? 0);
        });
        
        $cartTotal = $servicePrice + $productPrice  + $GiftPrice;
        
        $discountTotal = $cartservice->sum(fn($item) =>
            $item->services->sum(fn($s) => $s->discount_amount ?? 0)
        );

    
        $finalPrice = $cartTotal - $discountTotal;

        // Coupons already applied per service from the cart, shown in the payment summary
        $serviceCoupons = $cartservice
            ->flatMap(fn($item) => $item->services)
            ->filter(fn($s) => filled($s->coupon_code))
            ->groupBy('coupon_code')
            ->map(fn($group) => round((float) $group->sum('discount_amount'), 2))
            ->all();

        $serviceCount = $cartservice->sum(fn($item) => $item->services->count());
        
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

        return view('frontend::payment', compact('cartservice' , 'cartproduct' , 'finalPrice' , 'discountTotal' , 'serviceCount' , 'productCount' , 'productPrice' , 'GifttCount' , 'wallet' , 'loyaltyBalance' , 'branches', 'paymentMethods', 'gatewayDiscounts', 'tapPaymentSources', 'defaultPaymentMethod', 'defaultPaymentSource', 'serviceCoupons'));
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
            'urpay', 'applepay_urpay' => app(UrPayPaymentStrategy::class),
            'tabby' => app(TabbyPaymentStrategy::class),
            'tamara' => app(TamaraPaymentStrategy::class),
            default => throw ValidationException::withMessages([
                'paymentMethod' => __('messages.invalid_payment_method'),
            ]),
        };

        $idempotencyKey = $request->header('Idempotency-Key');
        $cacheKey = null;

        if ($idempotencyKey) {
            $userId = auth()->id() ?? 0;
            $cacheKey = "idemp_pay_{$userId}_{$idempotencyKey}";
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                $cachedResponse = \Illuminate\Support\Facades\Cache::get($cacheKey);
                if (is_array($cachedResponse)) {
                    return response()->json($cachedResponse['data'], $cachedResponse['status']);
                }
            }
        }

        // One payment start at a time per user, and never while a previous attempt may
        // still be paid (prevents charging the same cart twice).
        $guardUserId = (int) (auth()->id() ?? 0);
        $startLock = $guardUserId > 0 ? \Illuminate\Support\Facades\Cache::lock("pay_start_user_{$guardUserId}", 120) : null;

        if ($startLock && ! $startLock->get()) {
            return $this->blockedPaymentResponse($request, 'payment_already_starting', null);
        }

        try {
            if ($guardUserId > 0) {
                $blocking = app(\App\Services\Payment\PaymentAttemptCancelService::class)->blockingAttemptFor($guardUserId, $request);
                if ($blocking) {
                    return $this->blockedPaymentResponse(
                        $request,
                        $blocking->status === \App\Models\PaymentAttempt::STATUS_PAID ? 'previous_payment_completed' : 'previous_payment_in_progress',
                        $blocking
                    );
                }
            }

            $response = $strategy->pay($request, $isPayNow);
        } finally {
            $startLock?->release();
        }

        if ($cacheKey && $response instanceof \Illuminate\Http\JsonResponse) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'data' => $response->getData(true)
            ], now()->addHours(24));
        }

        return $response;
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

        $checkMethod = $method === 'applepay_urpay' ? 'urpay' : $method;

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
