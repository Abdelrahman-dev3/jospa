<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Backend\PaymentRequestController;
use App\Models\PaymentRequest;
use App\Models\PaymentRequestAttempt;
use App\Services\PaymentRequest\OdooPaymentInvoiceSyncService;
use App\Services\PaymentRequest\PaymentRequestService;
use Illuminate\Http\Request;

/**
 * Customer side of a payment link (no login; the 48-char token is the secret).
 * Opening or returning to these pages never marks anything as paid by itself:
 * only the server-to-server HyperPay lookup in PaymentRequestService does.
 */
class PaymentRequestPublicController extends Controller
{
    public function __construct(private PaymentRequestService $service) {}

    /** GET /pay/invoice/{token} */
    public function show(string $token)
    {
        $paymentRequest = $this->find($token);

        // Customer may be back from the gateway before anything else checked it.
        if ($paymentRequest->status !== PaymentRequest::STATUS_PAID) {
            $paymentRequest = $this->service->refreshOpenAttempts($paymentRequest);
        }

        $lastAttempt = $paymentRequest->attempts()->latest('id')->first();

        return response()
            ->view('frontend.payment-request.show', [
                'paymentRequest' => $paymentRequest,
                'lastAttempt' => $lastAttempt,
                'brands' => PaymentRequestService::BRANDS,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** POST /pay/invoice/{token} — open (or reuse) a HyperPay checkout for the stored amount. */
    public function pay(Request $request, string $token)
    {
        $paymentRequest = $this->find($token);

        $data = $request->validate(['brand' => 'required|in:'.implode(',', PaymentRequestService::BRANDS)]);

        try {
            $attempt = $this->service->startCheckout($paymentRequest, $data['brand']);
        } catch (\DomainException $e) {
            return redirect()->route('payment-request.show', $token)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('payment-request.show', $token)->with('error', __('payment_requests.errors.gateway'));
        }

        [$brands, $labels] = $attempt->brand === 'MADA'
            ? ['MADA', ['Mada']]
            : ['VISA MASTER', ['Visa', 'Mastercard']];

        return response()
            ->view('frontend.payment-status.hyperpay', [
                'widgetScriptUrl' => $this->service->widgetScriptUrl($attempt),
                'brands' => $brands,
                'brandLabels' => $labels,
                'resultUrl' => route('payment-request.return', ['attempt' => $attempt->uuid]),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * GET|POST /pay/invoice/return/{attempt}
     * HyperPay sends the shopper here. Its query parameters (id, resourcePath) are ignored:
     * the server looks up its own checkout id.
     */
    public function return(string $attempt)
    {
        $model = PaymentRequestAttempt::where('uuid', $attempt)->with('paymentRequest')->first();

        if (! $model || ! $model->paymentRequest) {
            abort(404);
        }

        $model = $this->service->refresh($model, force: true);

        $flash = match ($model->status) {
            PaymentRequestAttempt::STATUS_PAID => ['success', __('payment_requests.public.paid_now')],
            PaymentRequestAttempt::STATUS_PENDING => ['info', __('payment_requests.public.processing')],
            default => ['error', __('payment_requests.public.not_completed')],
        };

        return redirect()
            ->route('payment-request.show', $model->paymentRequest->token)
            ->with($flash[0], $flash[1]);
    }

    /** GET /pay/invoice/{token}/invoice.pdf — the Odoo tax invoice, once the request is paid. */
    public function invoicePdf(string $token, OdooPaymentInvoiceSyncService $odoo)
    {
        $paymentRequest = $this->find($token);
        abort_if($paymentRequest->status !== PaymentRequest::STATUS_PAID, 404);

        return PaymentRequestController::pdfResponse($paymentRequest, $odoo);
    }

    private function find(string $token): PaymentRequest
    {
        return PaymentRequest::where('token', $token)->firstOrFail();
    }
}
