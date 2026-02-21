<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use App\Services\TapPaymentService;

class CardPaymentStrategy
{
    public function pay(Request $request, string $typePage)
    {
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
            'payment_source' => $request->payment_source,
            'final_before_sub' => $request->total ?? 0,
            'discountAmount' => $request->discountAmount ?? 0,
            'cart_ids' => [],
            'gift_ids' => [],
        ];

        $calculator = app(PaymentCalculatorService::class);
        $totalData  = $calculator->calculateTotal($typePage, $request->invoiceCopon);
        if (isset($totalData['error'])) {
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
            return redirect()->back()->with('error', $subResult['error']);
        }

        $remainingAmount = $subResult['remaining_amount'];

        if ($remainingAmount <= 0) {
            try {
                $finalizer = app(PaymentFinalizerService::class);
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
                    true
                );
                $subMethodService->apply($data['user_id'], $request, $data['final_before_sub'] , true);

                return view('components.frontend.status.CAPTURED');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        session(['tap_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        $paymentUrl = $this->createTapCharge($request , $remainingAmount);
        
        if (!$paymentUrl) {
            throw new \Exception('Tabby payment not available');
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
        $user = auth()->user();
        $amount = $remainingAmount;
        $page_type = $data['page'];
        $source  = $data['payment_source'];

        if (!$amount || !is_numeric($amount)) {
            return redirect()->back()->with('error', __('messages.invalid_amount'));
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
            return "Error: " . json_encode($charge);
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
            return view('components.frontend.status.FAILED', $datas);
        };
    

        if (!isset($charge['status'])) {
            return $failed("خطأ غير متوقع: " . json_encode($charge), '', $redirectToPayment ? '/payment?ids=1' : null);
        }

    
        $status = $charge['status'];
        switch ($status) {
            case "CAPTURED":
                $finalizer = app(PaymentFinalizerService::class);
                $fakeRequest = new Request([
                    'wallet'    => $data['submethods']['wallet'] ?? false,
                    'loyalty'   => $data['submethods']['loyalty'] ?? false,
                    'gift_code' => $data['submethods']['gift_code'] ?? null,
                ]);
                $invoiceId = $finalizer->finalizePayment(
                    $data['user_id'],
                    $data['final_before_sub'],
                    $data['tax'],
                    $data['discountAmount'],
                    $page_type,
                    $data['cart_ids'] ?? [],
                    $data['gift_ids'] ?? [],
                    $data['payment_method'] ?? "Sub Methods",
                    $data['couponCode'] ?? "",
                    true
                );
                $subMethodService->apply($data['user_id'], $fakeRequest, $data['final_before_sub'] , true);
                session()->forget('tap_payment');
                return view('components.frontend.status.CAPTURED');
            case "FAILED":
                if ($redirectToPayment) {
                    session()->forget('tap_payment');
                    return $failed("خطأ غير متوقع: " . json_encode($charge), '', '/payment?ids=1');
                }
                session()->forget('tap_payment');
                return $failed(__('messages.failed_status'), __('messages.failed_message'));

            case "CANCELLED":
                session()->forget('tap_payment');
                return $failed(__('messages.cancelled_status'), __('messages.cancelled_message'));
        
            case "INITIATED":
                if ($redirectToPayment) {
                    session()->forget('tap_payment');
                    return $failed("خطأ غير متوقع: " . json_encode($charge), '', '/payment?ids=1');
                }
                session()->forget('tap_payment');
                return $failed(__('messages.initiated_status'), __('messages.initiated_message'));
            default:
                if ($redirectToPayment) {
                    session()->forget('tap_payment');
                    return $failed("خطأ غير متوقع: " . json_encode($charge), '', '/payment?ids=1');
                }
                session()->forget('tap_payment');
                return $failed(__('messages.unknown_status') . ": " . $status);
        }
    }
    
}
