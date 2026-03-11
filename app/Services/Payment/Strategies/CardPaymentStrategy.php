<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use App\Services\TapPaymentService;
use App\Support\FrontendPaymentSettings;
use Modules\Booking\Models\Booking;
use App\Models\GiftCard;
use Illuminate\Support\Facades\URL;

class CardPaymentStrategy
{
    public function pay(Request $request, string $typePage)
    {
        $selectedPaymentSource = $request->payment_source ?: FrontendPaymentSettings::defaultTapPaymentSource();

        if (! FrontendPaymentSettings::isEnabledTapSource($selectedPaymentSource)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.invalid_payment_method'),
                ], 422);
            }

            return redirect()->back()->with('error', __('messages.invalid_payment_method'));
        }

        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => 'tap',
            'couponCode' => $request->invoiceCopon ?? '',
            'submethods' => [
                'wallet' => (bool) $request->wallet,
                'loyalty' => (bool) $request->loyalty,
                'gift_code' => $request->gift_code,
            ],
            'payment_source' => $selectedPaymentSource,
            'final_before_sub' => $request->total ?? 0,
            'discountAmount' => $request->discountAmount ?? 0,
            'cart_ids' => [],
            'gift_ids' => [],
        ];

        $calculator = app(PaymentCalculatorService::class);
        $totalData  = $calculator->calculateTotal($typePage, $request->invoiceCopon);
        if (isset($totalData['error'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $totalData['error'],
                ], 422);
            }
            return redirect()->back()->with('error', $totalData['error']);
        }

        $data['final_before_sub'] = $totalData['total'];
        $data['discountAmount'] = $totalData['discountAmount'];
        $data['tax'] = $totalData['tax'];
        $data['cart_ids'] = $totalData['cart_ids'];
        $data['gift_ids'] = $totalData['gift_ids'];

        $subMethodService = app(PaymentSubMethodsService::class);
        $subResult = $subMethodService->apply($data['user_id'], $request, $data['final_before_sub']);

        if (isset($subResult['error'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $subResult['error'],
                ], 422);
            }
            return redirect()->back()->with('error', $subResult['error']);
        }

        $remainingAmount = $subResult['remaining_amount'];

        if ($remainingAmount <= 0) {
            try {
                $finalizer = app(PaymentFinalizerService::class);
                $subPayments = array_merge($subResult ?? [], [
                    'gift_code' => $request->get('gift_code'),
                ]);
                $invoiceId = $finalizer->finalizePayment(
                    $data['user_id'],
                    $data['final_before_sub'],
                    $data['tax'],
                    $data['discountAmount'],
                    $typePage,
                    $totalData['cart_ids'] ?? [],
                    $totalData['gift_ids'] ?? [],
                    $data['payment_method'] ?? "Sub Methods",
                    $data['couponCode'] ?? "",
                    true,
                    $subPayments
                );
                $subMethodService->apply($data['user_id'], $request, $data['final_before_sub'] , true);

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => true,
                        'message' => 'Payment completed using sub methods.',
                        'data' => [
                            'paid' => true,
                            'amount' => $data['final_before_sub'],
                            'payment_method' => $data['payment_method'],
                        ],
                    ]);
                }

                return view('frontend.payment-status.captured');
            } catch (\Exception $e) {
                if ($request->expectsJson()) {
                    return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
                }
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        if ($request->expectsJson()) {
            $tap = new TapPaymentService();
            $redirectUrl = $this->buildApiRedirectUrl($request, $data['user_id'], $data['couponCode'] ?? '');
            $charge = $tap->createCharge(
                $remainingAmount,
                [
                    "name"         => auth()->user()->first_name . auth()->user()->last_name,
                    "country_code" => "966",
                    "phone"        => auth()->user()->mobile,
                    "method"       => $data['payment_source'] ?? 'src_card',
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

            return response()->json([
                'status' => true,
                'message' => 'Redirect to payment gateway.',
                'data' => [
                    'payment_url' => $paymentUrl,
                    'charge_id' => $charge['id'] ?? null,
                    'amount' => $remainingAmount,
                    'payment_method' => $data['payment_method'],
                    'discount_amount' => $data['discountAmount'] ?? 0,
                ],
            ]);
        }

        session(['tap_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        try {
            $paymentUrl = $this->createTapCharge($request , $remainingAmount);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        
        $lang = app()->getLocale() ?? 'en';
        
        $paymentUrl = preg_replace('/([&?])language=[^&]+/', '$1language=' . $lang, $paymentUrl);
        
        if (!str_contains($paymentUrl, 'language=')) {
            $paymentUrl .= (str_contains($paymentUrl, '?') ? '&' : '?') . 'language=' . $lang;
        }
        
        return redirect()->away($paymentUrl);
    }
    
    private function createTapCharge(Request $request , float $remainingAmount)
    {
        $tap = new TapPaymentService();
        
        $data = session('tap_payment');
        if (!$data) {
            throw new \Exception('Tap session missing');
        }
        $user = auth()->user();
        $amount = $remainingAmount;
        $source  = $data['payment_source'] ?? 'src_card';

        if (!$amount || !is_numeric($amount)) {
            throw new \Exception(__('messages.invalid_amount'));
        }
        
        $charge = $tap->createCharge(
            amount: $amount,
            customerData: [
                "name"         => $user->first_name . $user->last_name ,
                "country_code" => "966",
                "phone"        => $user->mobile,
                "method"       => $source, 
            ],
            redirectUrl: route('tap.callback')
        );

        if (!isset($charge['transaction']['url'])) {
            throw new \Exception('Tap charge URL not found');
        }
        
        return $charge['transaction']['url'];
    }


    public function callback(Request $request)
    {
        $tap = new TapPaymentService();
        $subMethodService = app(PaymentSubMethodsService::class);

        $data = session('tap_payment');
        
        if (!$data || $data['user_id'] !== auth()->id()) {
            abort(403);
        }
        
        $chargeId = $request->tap_id;
        $finalBeforeSubMethods = $data['final_before_sub'];
        $tax = $data['tax'];
        $page_type = $data['page'];
        $discountAmount = $data['discountAmount'] ?? 0;

        if (!$chargeId) {
            return "خطأ: لم يتم العثور على معرف العملية tap_id";
        }
    
        $charge = $tap->getCharge($chargeId);
        
        $failed = function($message, $sub = '', $redirect = null) {
            $datas = [ 'message' => $message, 'sub' => $sub];
            if ($redirect) {
                $datas['redirect'] = $redirect;
            }
            return view('frontend.payment-status.failed', $datas);
        };
    

        if (!isset($charge['status'])) {
            return $failed("Unexpected response: " . json_encode($charge), '', null);
        }

        $expectedAmount = $data['amount'] ?? null;
        $paidAmount = $charge['amount'] ?? null;
        if ($expectedAmount !== null && $paidAmount !== null) {
            if (abs((float) $paidAmount - (float) $expectedAmount) > 0.01) {
                session()->forget('tap_payment');
                return $failed('Paid amount mismatch', '', null);
            }
        }

        $status = $charge['status'];
        switch ($status) {
            case "CAPTURED":
                if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                    session()->forget('tap_payment');
                    return view('frontend.payment-status.captured');
                }
                $finalizer = app(PaymentFinalizerService::class);
                $fakeRequest = new Request([
                    'wallet'    => $data['submethods']['wallet'] ?? false,
                    'loyalty'   => $data['submethods']['loyalty'] ?? false,
                    'gift_code' => $data['submethods']['gift_code'] ?? null,
                ]);
                $subResult = $subMethodService->apply($data['user_id'], $fakeRequest, $data['final_before_sub']);
                if (isset($subResult['error'])) {
                    return $failed($subResult['error'], '', null);
                }
                $subPayments = array_merge($subResult ?? [], [
                    'gift_code' => $data['submethods']['gift_code'] ?? null,
                ]);
                $finalizer->finalizePayment(
                    $data['user_id'],
                    $data['final_before_sub'],
                    $data['tax'],
                    $data['discountAmount'],
                    $page_type,
                    $data['cart_ids'] ?? [],
                    $data['gift_ids'] ?? [],
                    $data['payment_method'] ?? "Sub Methods",
                    $data['couponCode'] ?? "",
                    true,
                    $subPayments
                );
                $subMethodService->apply($data['user_id'], $fakeRequest, $data['final_before_sub'] , true);
                session()->forget('tap_payment');
                return view('frontend.payment-status.captured');
            case "FAILED":
                session()->forget('tap_payment');
                return $failed(__('messages.failed_status'), __('messages.failed_message'));

            case "CANCELLED":
                session()->forget('tap_payment');
                return $failed(__('messages.cancelled_status'), __('messages.cancelled_message'));
        
            case "INITIATED":
                session()->forget('tap_payment');
                return $failed(__('messages.initiated_status'), __('messages.initiated_message'));
            default:
                session()->forget('tap_payment');
                return $failed(__('messages.unknown_status') . ": " . $status);
        }
    }

    private function isAlreadyFinalized(array $cartIds, array $giftIds): bool
    {
        if (empty($cartIds) && empty($giftIds)) {
            return false;
        }

        if (!empty($cartIds)) {
            $paid = Booking::whereIn('id', $cartIds)->where('payment_status', 1)->count();
            if ($paid !== count($cartIds)) {
                return false;
            }
        }

        if (!empty($giftIds)) {
            $paidGifts = GiftCard::whereIn('id', $giftIds)->where('payment_status', 1)->count();
            if ($paidGifts !== count($giftIds)) {
                return false;
            }
        }

        return true;
    }

    private function buildApiRedirectUrl(Request $request, int $userId, ?string $couponCode): string
    {
        $params = array_filter([
            'user_id' => $userId,
            'coupon_code' => $couponCode,
            'wallet' => $request->boolean('wallet') ? 1 : null,
            'loyalty' => $request->boolean('loyalty') ? 1 : null,
            'gift_code' => $request->get('gift_code'),
            'payment_method' => 'card',
            'discount_amount' => $request->get('discount_amount', $request->get('discountAmount')),
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        return URL::temporarySignedRoute(
            'api.cart.payment.success',
            now()->addMinutes(30),
            $params
        );
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
    
}
