<?php

namespace App\Services\Payment\Strategies;

use App\Models\GiftCard;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentFinalizerService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;

abstract class BasePaymentStrategy
{
    protected function preparePaymentFlow(Request $request, string $typePage, string $paymentMethod): array
    {
        $data = [
            'user_id' => auth()->id(),
            'page' => $typePage,
            'payment_method' => $paymentMethod,
            'couponCode' => $request->invoiceCopon ?? $request->coupon_code ?? '',
            'submethods' => [
                'wallet' => (bool) $request->wallet,
                'loyalty' => (bool) $request->loyalty,
                'gift_code' => $request->gift_code,
            ],
            'final_before_sub' => $request->total ?? $request->total_price ?? 0,
            'discountAmount' => $request->discountAmount ?? $request->discount_amount ?? 0,
            'couponDiscountAmount' => 0,
            'paymentGatewayDiscountAmount' => 0,
            'paymentGatewayDiscountMethod' => null,
            'paymentGatewayDiscountLabel' => null,
            'cart_ids' => [],
            'gift_ids' => [],
        ];

        $calculator = app(PaymentCalculatorService::class);
        $totalData = $calculator->calculateTotal($typePage, $request->invoiceCopon, $paymentMethod);
        if (isset($totalData['error'])) {
            return [
                'response' => $this->respondPaymentInputError($request, $totalData['error']),
            ];
        }

        $data['final_before_sub'] = $totalData['total'];
        $data['discountAmount'] = $totalData['discountAmount'];
        $data['couponDiscountAmount'] = $totalData['couponDiscountAmount'] ?? 0;
        $data['paymentGatewayDiscountAmount'] = $totalData['paymentGatewayDiscountAmount'] ?? 0;
        $data['paymentGatewayDiscountMethod'] = $totalData['paymentGatewayDiscountMethod'] ?? null;
        $data['paymentGatewayDiscountLabel'] = $totalData['paymentGatewayDiscountLabel'] ?? null;
        $data['tax'] = $totalData['tax'];
        $data['cart_ids'] = $totalData['cart_ids'];
        $data['gift_ids'] = $totalData['gift_ids'];

        $subMethodService = app(PaymentSubMethodsService::class);
        $subResult = $subMethodService->apply($data['user_id'], $request, $data['final_before_sub']);

        if (isset($subResult['error'])) {
            return [
                'response' => $this->respondPaymentInputError($request, $subResult['error']),
            ];
        }

        return [
            'data' => $data,
            'totalData' => $totalData,
            'subResult' => $subResult,
            'remainingAmount' => $subResult['remaining_amount'],
        ];
    }

    protected function commitFinalizedPayment(int $userId, Request $request, array $paymentData, array $subResult): int
    {
        $finalizer = app(PaymentFinalizerService::class);
        $subMethodService = app(PaymentSubMethodsService::class);
        $subPayments = array_merge($subResult, [
            'gift_code' => $request->get('gift_code'),
            'coupon_discount_amount' => $paymentData['couponDiscountAmount'] ?? 0,
            'payment_gateway_discount_amount' => $paymentData['paymentGatewayDiscountAmount'] ?? 0,
            'payment_gateway_discount_method' => $paymentData['paymentGatewayDiscountMethod'] ?? null,
            'payment_gateway_discount_label' => $paymentData['paymentGatewayDiscountLabel'] ?? null,
        ]);

        $invoiceId = $finalizer->finalizePayment(
            $userId,
            $paymentData['final_before_sub'],
            $paymentData['tax'],
            $paymentData['discountAmount'],
            $paymentData['page'],
            $paymentData['cart_ids'] ?? [],
            $paymentData['gift_ids'] ?? [],
            $paymentData['payment_method'] ?? 'Sub Methods',
            $paymentData['couponCode'] ?? '',
            true,
            $subPayments
        );

        $subMethodService->apply($userId, $request, $paymentData['final_before_sub'], true);

        return $invoiceId;
    }

    protected function respondSubMethodOnlySuccess(Request $request, array $paymentData)
    {
        return $this->respondSuccess($request, 'Payment completed using sub methods.', [
            'paid' => true,
            'amount' => $paymentData['final_before_sub'],
            'payment_method' => $paymentData['payment_method'],
        ]);
    }

    protected function respondPaymentInputError(Request $request, string $message, int $status = 422)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()
            ->withInput()
            ->withErrors(['payment' => $message])
            ->with('error', $message);
    }

    protected function respondPayException(Request $request, \Throwable $e, int $status = 500)
    {
        report($e);
        $message = $this->presentableErrorMessage($e);

        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()
            ->withInput()
            ->withErrors(['payment' => $message])
            ->with('error', $message);
    }

    protected function isAlreadyFinalized(array $cartIds, array $giftIds): bool
    {
        if (empty($cartIds) && empty($giftIds)) {
            return false;
        }

        if (!empty($cartIds)) {
            $paid = Booking::whereIn('id', $cartIds)->paid()->count();
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

    protected function isClientDiscountMatching(?float $clientDiscount, float $expectedDiscount): bool
    {
        if ($clientDiscount === null) {
            return true;
        }

        return abs($clientDiscount - $expectedDiscount) <= 0.01;
    }

    protected function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->is('api/*');
    }

    protected function respondSuccess(Request $request, string $message, array $data = [])
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data,
            ]);
        }

        return view('frontend.payment-status.captured');
    }

    protected function respondFailure(Request $request, string $message, int $status = 400)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], $status);
        }

        return view('frontend.payment-status.failed', ['message' => $message]);
    }

    protected function presentableErrorMessage(\Throwable $e): string
    {
        return trim($e->getMessage()) !== '' ? $e->getMessage() : __('messages.payment_failed');
    }
}
