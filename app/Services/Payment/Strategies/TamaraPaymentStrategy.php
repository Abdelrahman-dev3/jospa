<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Support\Facades\Http;

class TamaraPaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => 'tamara',
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

        session(['tamara_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        $paymentUrl = $this->createTamaraCheckout($remainingAmount);
        
        if (!$paymentUrl) {
            throw new \Exception('Tabby payment not available');
        }
        
        return redirect()->away($paymentUrl);
    }

    private function createTamaraCheckout(float $amount): string
    {
        $user = auth()->user();
        
        $secretKey = config('tamara.secret_key');
        $baseUrl   = config('tamara.base_url', 'https://api.tamara.co');
        $currency  = 'SAR';
    
        if (!$secretKey) {
            throw new \Exception('Tamara configuration error');
        }
    
        $session = session('tamara_payment');
    
        if (!$session) {
            throw new \Exception('Tamara session missing');
        }
    
        $cartIds    = $session['cart_ids'] ?? [];
        $invoiceRef = 'INV-' . implode('-', $cartIds);
    
        $payload = [
            'total_amount' => [
                'amount'   => round($amount, 2),
                'currency' => $currency,
            ],
            'shipping_amount' => [
                'amount'   => 0,
                'currency' => $currency,
            ],
            'tax_amount' => [
                'amount'   => 0,
                'currency' => $currency,
            ],
    
            'order_reference_id' => $invoiceRef,
            'order_number'       => $invoiceRef,
            'description'        => "Invoice #{$invoiceRef}",
            'country_code'       => 'SA',
            'payment_type'       => 'PAY_BY_INSTALMENTS',
    
            'consumer' => [
                'first_name'   => $user->username,
                'phone_number' => $user->mobile,
            ],
    
            'merchant_url' => [
                'success' => route('tamara.success'),
                'failure' => route('tamara.failure'),
                'cancel'  => route('tamara.cancel'),
            ],
    
            'platform'  => 'web',
            'is_mobile' => false,
        ];
    
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($baseUrl . '/checkout', $payload);


    
        if (!$response->successful()) {
            throw new \Exception('Tamara checkout failed: ' . $response->body());
        }
    
        $data = $response->json();
    
        if (empty($data['checkout_url'])) {
            throw new \Exception('Tamara checkout URL not found');
        }
    
        session()->put('tamara_payment.checkout_id', $data['checkout_id']);
    
        return $data['checkout_url'];
    }

    public function success(Request $request)
    {
        $data = session('tamara_payment');

        if (!$data || $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        $checkoutId = $request->checkout_id ?? $data['checkout_id'] ?? null;

        if (!$checkoutId) {
            return redirect()->route('home')->with('error', 'Tamara checkout id missing');
        }

        $response = Http::withToken(config('tamara.secret_key'))
            ->get(config('tamara.base_url') . "/api/v2/checkout/{$checkoutId}");

        if (!$response->successful()) {
            return redirect()->back()->with('error', 'Failed to verify Tamara payment');
        }

        $checkout = $response->json();
        $status   = $checkout['status'] ?? null;

        if ($status !== 'approved') {
            session()->forget('tamara_payment');
            return view('components.frontend.status.FAILED');
        }

        app(PaymentFinalizerService::class)->finalizePayment(
            auth()->id(),
            $data['final_before_sub'],
            $data['tax'],
            $data['discountAmount'],
            $data['page'],
            $data['cart_ids'],
            $data['gift_ids'],
            $data['payment_method'] ?? "Sub Methods",
            $data['couponCode'] ?? "",
            true
        );

        app(PaymentSubMethodsService::class)->apply(
            auth()->id(),
            new Request($data['submethods']),
            $data['final_before_sub'],
            true
        );

        session()->forget('tamara_payment');

        return view('components.frontend.status.CAPTURED');
    }

    public function failure()
    {
        session()->forget('tamara_payment');

        return view('components.frontend.status.FAILED', [
            'message' => __('messages.payment_failed')
        ]);
    }

    public function cancel()
    {
        session()->forget('tamara_payment');

        return view('components.frontend.status.FAILED', [
            'message' => __('messages.payment_cancelled')
        ]);
    }

}
