<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\Payment\Strategies\CardPaymentStrategy;
use App\Services\Payment\Strategies\TabbyPaymentStrategy;
use App\Services\Payment\Strategies\TamaraPaymentStrategy;

class PaymentchanalController extends Controller
{
    /**
     * Main payment entry
     */
    public function payment(Request $request)
    {
        $method   = $request->paymentMethod;
        $typePage = $request->ids ? 'payment' : 'cart';

        $strategy = match ($method) {
            'card'   => app(CardPaymentStrategy::class),
            'tabby'  => app(TabbyPaymentStrategy::class),
            'tamara' => app(TamaraPaymentStrategy::class),
            default  => throw ValidationException::withMessages([
                'paymentMethod' => __('messages.invalid_payment_method')
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
}
