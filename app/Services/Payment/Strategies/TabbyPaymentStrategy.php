<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Support\Facades\Http;

class TabbyPaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => 'tabby',
            'couponCode' => $request->invoiceCopon ?? '',
            'submethods' => [
                'wallet' => (bool) $request->wallet,
                'loyalty' => (bool) $request->loyalty,
                'gift_code' => $request->gift_code,
            ],
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

        session(['tabby_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        $data = $this->createTabbyCharge($request, $typePage , $remainingAmount);

        $paymentUrl = $data['configuration']['available_products']['installments'][0]['web_url'] ?? $data['configuration']['available_products']['pay_later'][0]['web_url'] ?? null;
        
        if (!$paymentUrl) {
            throw new \Exception('Tabby payment not available');
        }
        
        return redirect()->away($paymentUrl);

    }

    private function createTabbyCharge(Request $request, string $typePage , float $remainingAmount)
    {
        $secretKey    = config('tabby.secret_key');
        $merchantCode = config('tabby.merchant_code');
        $baseUrl      = 'https://api.tabby.ai/api/v2/checkout';
    
        if (!$secretKey || !$merchantCode) {
            throw new \Exception('Tabby configuration error');
        }
    
        $user = auth()->user();
        $userId = $user->id;
    
        $calculator = app(\App\Services\Payment\PaymentCalculatorService::class);
        $totalData  = $calculator->calculateTotal($typePage, $request->invoiceCopon);
    
        if (isset($totalData['error'])) {
            throw new \Exception($totalData['error']);
        }
    
        $finalAmount = $remainingAmount;
    
        $cartIds = $totalData['cart_ids'] ?? [];

        $invoiceRef = 'INV-' . implode('-', $cartIds);
    
        $payload = [
            "merchant_code" => $merchantCode,
            "payment" => [
                "amount"      => $finalAmount,
                "currency"    => "SAR",
                "description" => "Invoice #{$invoiceRef}",
                "buyer" => [
                    "phone" => $user->mobile,
                    "name"  => $user->name,
                ],
            ],
            "order" => [
                "reference_id" => $invoiceRef,
            ],
            "lang" => app()->getLocale() ?? 'en',
            "merchant_urls" => [
                "success" => route('tabby.success', $invoiceRef),
                "fail" => route('tabby.fail', $invoiceRef),
                "cancel"  => route('tabby.cancel',  $invoiceRef),
            ],
        ];
    
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($baseUrl, $payload);
    
        if (!$response->successful()) {
            throw new \Exception('Tabby payment failed');
        }
    
        return $response->json();
    }


    public function callback(Request $request)
    {
        $data = session('tabby_payment');
        $subMethodService = app(PaymentSubMethodsService::class);

        if (!$data || $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        $tapId = $request->tap_id ?? $data['checkout_id'] ?? null;
        if (!$tapId) {
            return redirect()->back()->with('error', 'Tabby ID not found');
        }

        $secretKey = config('tabby.secret_key');
        $baseUrl = "https://api.tabby.ai/api/v2/checkout/{$tapId}";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
        ])->get($baseUrl);

        $charge = $response->json();

        if (!isset($charge['status'])) {
            return redirect()->back()->with('error', 'Unexpected Tabby response: ' . json_encode($charge));
        }

        $status = $charge['status'];

        $finalizer = app(PaymentFinalizerService::class);

        switch ($status) {
            case "CAPTURED":
                try {
                    $fakeRequest = new Request([
                        'wallet'    => $data['submethods']['wallet'] ?? false,
                        'loyalty'   => $data['submethods']['loyalty'] ?? false,
                        'gift_code' => $data['submethods']['gift_code'] ?? null,
                    ]);
                    $invoiceId = $finalizer->finalizePayment(
                        auth()->id(),
                        $data['final_before_sub'],
                        $data['tax'],
                        $data['discountAmount'],
                        $data['page'],
                        $data['cart_ids'] ?? [],
                        $data['gift_ids'] ?? [],
                        $data['payment_method'] ?? "Sub Methods",
                        $data['couponCode'] ?? "",
                        true
                    );
                    $subMethodService->apply(auth()->id(), $fakeRequest, $data['final_before_sub'] , true);
    
                    session()->forget('tabby_payment');
                    return view('components.frontend.status.CAPTURED');
                } catch (\Exception $e) {
                    session()->forget('tabby_payment');
                    return redirect()->back()->with('error', $e->getMessage());
                }
                return view('components.frontend.status.CAPTURED');
            case "FAILED":
            case "CANCELLED":
                session()->forget('tabby_payment');
                return view('components.frontend.status.FAILED', ['message' => $status]);
            default:
                session()->forget('tabby_payment');
                return view('components.frontend.status.FAILED', ['message' => 'Unknown status: ' . $status]);
        }
    }
}
