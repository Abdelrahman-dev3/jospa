<?php

namespace App\Services\Payment\Strategies;

use Illuminate\Http\Request;
use App\Models\PaymentAttempt;
use App\Services\Payment\PaymentCalculatorService;
use App\Services\Payment\PaymentSubMethodsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class TabbyPaymentStrategy extends BasePaymentStrategy
{
    public function pay(Request $request, $typePage)
    {
        $prepared = $this->preparePaymentFlow($request, $typePage, 'tabby');
        if (isset($prepared['response'])) {
            return $prepared['response'];
        }

        $data = $prepared['data'];
        $subResult = $prepared['subResult'];
        $remainingAmount = $prepared['remainingAmount'];

        if ($remainingAmount <= 0) {
            try {
                $this->commitFinalizedPayment($data['user_id'], $request, $data, $subResult);
                return $this->respondSubMethodOnlySuccess($request, $data);
            } catch (\Throwable $e) {
                return $this->respondPayException($request, $e);
            }
        }

        [, $data] = $this->createPaymentAttempt($request, $data, 'tabby', $remainingAmount);

        if ($request->expectsJson()) {
            $merchantUrls = $this->buildSignedMerchantUrls($request, $typePage, $data);
            try {
                $invoiceRef = $this->buildTabbyInvoiceReference($data);
                $chargeData = $this->createTabbyCharge($request, $typePage, $remainingAmount, $merchantUrls, [
                    'cart_ids' => $data['cart_ids'] ?? [],
                    'gift_ids' => $data['gift_ids'] ?? [],
                    'product_ids' => $data['product_ids'] ?? [],
                    'invoice_ref' => $invoiceRef,
                    'user_id' => $data['user_id'] ?? auth()->id(),
                ]);
            } catch (\Exception $e) {
                $this->markPaymentAttemptFailed($data['attempt_id'] ?? null, $e->getMessage());
                return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
            }

            $this->markPaymentAttemptPending($data['attempt_id'] ?? null, [
                'merchant_reference' => $invoiceRef,
                'gateway_checkout_id' => $chargeData['id'] ?? $chargeData['checkout_id'] ?? null,
                'gateway_order_id' => data_get($chargeData, 'order.reference_id'),
                'gateway_response' => $chargeData,
            ]);

            // Cache backup
            if (!empty($data['attempt_id'])) {
                Cache::put('tabby_payment_' . $data['attempt_id'], $data, now()->addMinutes(40));
            }

            $paymentUrl = $this->extractTabbyPaymentUrl($chargeData);
            
            if (!$paymentUrl) {
                return response()->json([
                    'status' => false,
                    'message' => $this->resolveTabbyUnavailableMessage($chargeData),
                ], 422);
            }

            return response()->json([
                'status' => true,
                'message' => 'Redirect to payment gateway.',
                'data' => [
                    'payment_url' => $paymentUrl,
                    'amount' => $remainingAmount,
                    'payment_method' => $data['payment_method'],
                    'discount_amount' => $data['discountAmount'] ?? 0,
                ],
            ]);
        }

        session(['tabby_payment' => array_merge($data, ['amount' => $remainingAmount])]);

        try {
            $invoiceRef = $this->buildTabbyInvoiceReference(session('tabby_payment', []));
            $data = $this->createTabbyCharge($request, $typePage, $remainingAmount, null, [
                'cart_ids' => session('tabby_payment.cart_ids', []),
                'gift_ids' => session('tabby_payment.gift_ids', []),
                'product_ids' => session('tabby_payment.product_ids', []),
                'invoice_ref' => $invoiceRef,
                'user_id' => session('tabby_payment.user_id', auth()->id()),
            ]);
        } catch (\Exception $e) {
            $this->markPaymentAttemptFailed(session('tabby_payment.attempt_id'), $e->getMessage());
            session()->forget('tabby_payment');
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->markPaymentAttemptPending(session('tabby_payment.attempt_id'), [
            'merchant_reference' => $invoiceRef,
            'gateway_checkout_id' => $data['id'] ?? $data['checkout_id'] ?? null,
            'gateway_order_id' => data_get($data, 'order.reference_id'),
            'gateway_response' => $data,
        ]);

        // Cache backup
        if (session('tabby_payment.attempt_id')) {
            Cache::put('tabby_payment_' . session('tabby_payment.attempt_id'), session('tabby_payment'), now()->addMinutes(40));
        }

        $paymentUrl = $this->extractTabbyPaymentUrl($data);
        
        if (!$paymentUrl) {
            $message = $this->resolveTabbyUnavailableMessage($data);
            $this->markPaymentAttemptFailed(session('tabby_payment.attempt_id'), $message, [
                'merchant_reference' => $invoiceRef,
                'gateway_checkout_id' => $data['id'] ?? $data['checkout_id'] ?? null,
                'gateway_order_id' => data_get($data, 'order.reference_id'),
                'gateway_response' => $data,
            ]);
            session()->forget('tabby_payment');
            return redirect()->back()->with('error', $message);
        }
        
        return redirect()->away($paymentUrl);

    }

    private function createTabbyCharge(Request $request, string $typePage, float $remainingAmount, ?array $merchantUrls = null, array $context = [])
    {
        $secretKey    = config('tabby.secret_key');
        $merchantCode = config('tabby.merchant_code');
        $baseUrl      = rtrim(config('tabby.base_url', 'https://api.tabby.ai'), '/') . '/api/v2/checkout';

        if (!$secretKey || !$merchantCode) {
            throw new \Exception('Tabby configuration error');
        }

        $user = auth()->user();
        if (!$user && !empty($context['user_id'])) {
            $user = User::find((int) $context['user_id']);
        }

        if (!$user) {
            throw new \Exception('Tabby user missing');
        }

        $cartIds = array_values(array_filter($context['cart_ids'] ?? []));
        $giftIds = array_values(array_filter($context['gift_ids'] ?? []));
        $productIds = array_values(array_filter($context['product_ids'] ?? []));

        // Use pre-computed cart data when available to avoid redundant DB queries.
        // Fall back to recalculating only if the caller didn't supply them.
        if (empty($cartIds) && empty($giftIds) && empty($productIds)) {
            $calculator = app(PaymentCalculatorService::class);
            $totalData  = $calculator->calculateTotal($typePage, $request->invoiceCopon, 'tabby');

            if (isset($totalData['error'])) {
                throw new \Exception($totalData['error']);
            }

            $cartIds    = $totalData['cart_ids'] ?? [];
            $giftIds    = $totalData['gift_ids'] ?? [];
            $productIds = $totalData['product_ids'] ?? [];
        }

        $invoiceRef = $context['invoice_ref'] ?? $this->buildTabbyInvoiceReference([
            'cart_ids' => $cartIds,
            'gift_ids' => $giftIds,
            'product_ids' => $productIds,
        ]);

        $buyerName = $user->full_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $email = config('tabby.mode')=='test'
    ? 'otp.success@tabby.ai'
    : $user->email;
        $payload = [
            'merchant_code' => $merchantCode,
            'payment'       => [
                'amount'      => number_format($remainingAmount, 2, '.', ''),
                'currency'    => 'SAR',
                'description' => "Invoice #{$invoiceRef}",
                'buyer'       => [
                    'phone' => $user->mobile,
                    'name'  => $buyerName,
                    'email' => $email,
                ],
                'order'       => [
                    'reference_id' => $invoiceRef,
                    'items' => [[
                        'title' => "Invoice #{$invoiceRef}",
                        'description' => 'Jospa checkout payment',
                        'quantity' => 1,
                        'unit_price' => number_format($remainingAmount, 2, '.', ''),
                        'category' => 'Services',
                        'reference_id' => $invoiceRef,
                    ]],
                ],
            ],
            'lang'          => app()->getLocale() ?? 'en',
            'merchant_urls' => $merchantUrls ?? [
                'success' => route('tabby.success', $invoiceRef),
                'failure' => route('tabby.fail', $invoiceRef),
                'cancel'  => route('tabby.cancel', $invoiceRef),
            ],
        ];

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($baseUrl, $payload);

        if (!$response->successful()) {
            throw new \Exception('Tabby payment failed: ' . $response->body());
        }

        $responseData = $response->json();
        $responseData = is_array($responseData) ? $responseData : [];
        $checkoutId   = $responseData['id'] ?? $responseData['checkout_id'] ?? null;

        if ($checkoutId && ! $this->extractTabbyPaymentUrl($responseData)) {
            $checkoutResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Accept' => 'application/json',
            ])->get(rtrim(config('tabby.base_url', 'https://api.tabby.ai'), '/') . '/api/v2/checkout/' . urlencode((string) $checkoutId));

            if ($checkoutResponse->successful() && is_array($checkoutResponse->json())) {
                $responseData = array_replace_recursive($responseData, $checkoutResponse->json());
            }
        }

        if ($checkoutId) {
            session()->put('tabby_payment.checkout_id', $checkoutId);
        }

        // Also persist the payment_id returned inside the payment object if present.
        // This is the correct ID for the /api/v2/payments/{payment_id} verification endpoint.
        $paymentId = data_get($responseData, 'payment.id') ?? data_get($responseData, 'payment_id') ?? null;
        if ($paymentId) {
            session()->put('tabby_payment.payment_id', $paymentId);
        }

        return $responseData;
    }

    private function buildTabbyInvoiceReference(array $context): string
    {
        $attemptId = (int) ($context['attempt_id'] ?? 0);
        if ($attemptId > 0) {
            return 'INV-' . $attemptId;
        }

        $cartIds = array_values(array_filter($context['cart_ids'] ?? []));
        $giftIds = array_values(array_filter($context['gift_ids'] ?? []));
        $productIds = array_values(array_filter($context['product_ids'] ?? []));

        $referenceParts = [];

        if (!empty($cartIds)) {
            $referenceParts[] = 'B' . implode('-', $cartIds);
        }

        if (!empty($giftIds)) {
            $referenceParts[] = 'G' . implode('-', $giftIds);
        }

        if (!empty($productIds)) {
            $referenceParts[] = 'P' . implode('-', $productIds);
        }

        if (empty($referenceParts)) {
            throw new \Exception('Tabby checkout context missing');
        }

        return 'INV-' . implode('_', $referenceParts);
    }

    private function extractTabbyPaymentUrl(array $chargeData): ?string
    {
        $candidatePaths = [
            'configuration.available_products.installments.0.web_url',
            'configuration.available_products.pay_later.0.web_url',
            'configuration.products.installments.web_url',
            'configuration.products.pay_later.web_url',
            'available_products.installments.0.web_url',
            'available_products.pay_later.0.web_url',
            'configuration.web_url',
            'payment.web_url',
            'checkout.web_url',
            'web_url',
            'url',
        ];

        foreach ($candidatePaths as $path) {
            $value = data_get($chargeData, $path);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveTabbyUnavailableMessage(array $chargeData): string
    {
        $status = $this->normalizeTabbyStatus(data_get($chargeData, 'status'));
        $reason = data_get($chargeData, 'rejection_reason')
            ?? data_get($chargeData, 'payment.rejection_reason')
            ?? data_get($chargeData, 'payment.status')
            ?? data_get($chargeData, 'message')
            ?? data_get($chargeData, 'error')
            ?? data_get($chargeData, 'errors.0.message');

        if (is_string($reason) && trim($reason) !== '') {
            return 'Tabby payment unavailable: ' . $reason;
        }

        if ($status !== '') {
            return 'Tabby payment unavailable: ' . $status;
        }

        return 'Tabby payment not available';
    }

    private function normalizeTabbyStatus($status): string
    {
        return strtolower(trim((string) $status));
    }

    private function isTabbySuccessfulStatus(string $status): bool
    {
        return in_array($status, ['authorized', 'closed', 'captured', 'approved'], true);
    }

    private function isTabbyFailureStatus(string $status): bool
    {
        return in_array($status, ['rejected', 'expired', 'failed', 'cancelled', 'canceled'], true);
    }

    private function captureTabbyPayment(string $paymentId, array $charge): array
    {
        $secretKey = config('tabby.secret_key');
        $baseUrl = rtrim(config('tabby.base_url', 'https://api.tabby.ai'), '/');
        //$orderReference = (string) (data_get($charge, 'order.reference_id') ?? $paymentId);
        $orderReference = (string) data_get($charge, 'order.reference_id')!='' ? data_get($charge, 'order.reference_id') : $paymentId;
        $amount = (float) (data_get($charge, 'amount') ?? data_get($charge, 'payment.amount') ?? 0);
        //dd((string) (data_get($charge, 'order.reference_id') ?? $paymentId);
        $payload = [
            'amount' => $amount,
            'reference_id' => $orderReference,
            'tax_amount' => (float) (data_get($charge, 'order.tax_amount') ?? 0),
            'shipping_amount' => (float) (data_get($charge, 'order.shipping_amount') ?? 0),
            'discount_amount' => (float) (data_get($charge, 'order.discount_amount') ?? 0),
            'items' => data_get($charge, 'order.items') ?: [[
                'title' => "Invoice #{$orderReference}",
                'description' => 'Service booking payment',
                'quantity' => 1,
                'unit_price' => $amount,
                'reference_id' => $orderReference,
            ]],
        ];

        //dd($payload);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post($baseUrl . '/api/v2/payments/' . $paymentId . '/captures', $payload);
        //dd($response->json());
        if (! $response->successful()) {
            throw new \Exception('Tabby capture failed: ' . $response->body());
        }

        $captureData = $response->json();

        return is_array($captureData) ? $captureData : [];
    }


    public function callback(Request $request)
    {
        $data = session('tabby_payment');
        
        // Resolve attempt_id first to get the cache key if session is missing
        $attemptId = $this->resolveAttemptId($request, $data);

        // Fallback: if session lost, try to recover from cache via attempt_id
        if (! $data && $attemptId) {
            $data = Cache::get('tabby_payment_' . $attemptId);
            if ($data) {
                \Log::info('Tabby: Recovered payment data from cache fallback.', ['attempt_id' => $attemptId]);
            }
        }

        $subMethodService = app(PaymentSubMethodsService::class);
        $attempt = $attemptId ? PaymentAttempt::find($attemptId) : null;

        if ($attempt && $attempt->status === PaymentAttempt::STATUS_PAID) {
            session()->forget('tabby_payment');
            return $this->respondSuccess($request, 'Payment already finalized.', [
                'invoice_id' => $attempt->invoice_id,
            ]);
        }

        if ($data && auth()->check() && $data['user_id'] !== auth()->id()) {
            abort(403);
        }

        // Tabby redirects the success URL with a `payment_id` query parameter.
        // Use it to call the correct payments verification endpoint: GET /api/v2/payments/{payment_id}.
        $paymentId = $request->get('payment_id')
            ?? $request->get('checkout_id')
            ?? $request->get('checkoutId')
            ?? $request->get('id')
            ?? session('tabby_payment.payment_id')
            ?? session('tabby_payment.checkout_id')
            ?? ($data['payment_id'] ?? $data['checkout_id'] ?? null);

        if (!$paymentId) {
            return redirect()->back()->with('error', 'Tabby payment ID not found');
        }

        $secretKey = config('tabby.secret_key');
        $baseUrl   = rtrim(config('tabby.base_url', 'https://api.tabby.ai'), '/')
                   . '/api/v2/payments/' . $paymentId;

        // Alias so all audit/logging references below continue to work.
        $checkoutId = $paymentId;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Accept'        => 'application/json',
        ])->get($baseUrl);

        $charge = $response->json();
        $charge = is_array($charge) ? $charge : [];

        if (!$response->successful() || !isset($charge['status'])) {
            $message = $this->resolveTabbyUnavailableMessage($charge);
            $this->markPaymentAttemptFailed($attemptId, $message, [
                'gateway_transaction_id' => $checkoutId,
                'gateway_checkout_id' => $checkoutId,
                'gateway_response' => $charge,
                'callback_payload' => $request->all(),
            ]);
            session()->forget('tabby_payment');
            return $this->respondFailure($request, $message, 502);
        }

        $normalizedStatus = $this->normalizeTabbyStatus($charge['status'] ?? null);

        if ($normalizedStatus === 'authorized') {
            try {
                $capturedCharge = $this->captureTabbyPayment((string) $paymentId, $charge);
                if (! empty($capturedCharge)) {
                    $charge = array_replace_recursive($charge, $capturedCharge);
                }
                $normalizedStatus = $this->normalizeTabbyStatus($charge['status'] ?? 'closed');
            } catch (\Throwable $e) {
                $this->markPaymentAttemptFailed($attemptId, $e->getMessage(), [
                    'gateway_transaction_id' => $checkoutId,
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $charge,
                    'callback_payload' => $request->all(),
                ]);
                session()->forget('tabby_payment');
                return $this->respondFailure($request, $e->getMessage(), 502);
            }
        }

        if ($this->isTabbySuccessfulStatus($normalizedStatus)) {
            switch ($normalizedStatus) {
                case 'authorized':
                case 'closed':
                case 'captured':
                case 'approved':
                if ($data) {
                    $payerUserId = (int) ($data['user_id'] ?? auth()->id());
                    if ($payerUserId <= 0) {
                        $this->markPaymentAttemptFailed($attemptId, 'Tabby user missing on callback', [
                            'gateway_transaction_id' => $checkoutId,
                            'gateway_checkout_id' => $checkoutId,
                            'gateway_response' => $charge,
                            'callback_payload' => $request->all(),
                        ]);
                        session()->forget('tabby_payment');
                        return $this->respondFailure($request, 'User not found.', 404);
                    }

                    if ($this->isAlreadyFinalized($data['cart_ids'] ?? [], $data['gift_ids'] ?? [])) {
                        $this->markPaymentAttemptPaid($attemptId, [
                            'gateway_transaction_id' => $checkoutId,
                            'gateway_checkout_id' => $checkoutId,
                            'gateway_response' => $charge,
                            'callback_payload' => $request->all(),
                        ]);
                        session()->forget('tabby_payment');
                        return $this->respondSuccess($request, 'Payment already finalized.');
                    }
                    try {
                        $fakeRequest = new Request([
                            'wallet'    => $data['submethods']['wallet'] ?? false,
                            'loyalty'   => $data['submethods']['loyalty'] ?? false,
                            'gift_code' => $data['submethods']['gift_code'] ?? null,
                            'gift_amount' => $data['submethods']['gift_amount'] ?? null,
                        ]);
                        $subResult = $subMethodService->apply($payerUserId, $fakeRequest, $data['final_before_sub']);
                        if (isset($subResult['error'])) {
                            $this->markPaymentAttemptFailed($attemptId, $subResult['error'], [
                                'gateway_transaction_id' => $checkoutId,
                                'gateway_checkout_id' => $checkoutId,
                                'gateway_response' => $charge,
                                'callback_payload' => $request->all(),
                            ]);
                            session()->forget('tabby_payment');
                            return $this->respondFailure($request, $subResult['error'], 422);
                        }
                        $invoiceId = DB::transaction(function () use ($payerUserId, $fakeRequest, $data, $subResult, $checkoutId, $charge, $attemptId, $request) {
                            return $this->commitFinalizedPayment($payerUserId, $fakeRequest, $data, $subResult, [
                                'attempt_id' => $attemptId,
                                'transaction_id' => $checkoutId,
                                'merchant_reference' => data_get($charge, 'order.reference_id'),
                                'checkout_id' => $checkoutId,
                                'order_id' => data_get($charge, 'order.reference_id'),
                                'gateway_response' => $charge,
                                'callback_payload' => $request->all(),
                            ], true); // sub-methods committed = true because $subMethodService->apply was called above
                        });
        
                        session()->forget('tabby_payment');
                        if ($attemptId) {
                            Cache::forget('tabby_payment_' . $attemptId);
                        }
                        return $this->respondSuccess($request, 'Payment captured successfully.', [
                            'invoice_id' => $invoiceId ?? null,
                        ]);
                    } catch (\Exception $e) {
                        $this->markPaymentAttemptFailed($attemptId, $e->getMessage(), [
                            'gateway_transaction_id' => $checkoutId,
                            'gateway_checkout_id' => $checkoutId,
                            'gateway_response' => $charge,
                            'callback_payload' => $request->all(),
                        ]);
                        session()->forget('tabby_payment');
                        if ($attemptId) {
                            Cache::forget('tabby_payment_' . $attemptId);
                        }
                        return $this->respondFailure($request, $e->getMessage(), 500);
                    }
                }

                $context = $this->resolveStatelessContext($request);
                if (!$context) {
                    return $this->respondFailure($request, 'Invalid payment callback.', 400);
                }

                $user = User::find($context['user_id']);
                if (!$user) {
                    return $this->respondFailure($request, 'User not found.', 404);
                }

                $calculator = app(PaymentCalculatorService::class);
                $totalData = $calculator->calculateTotal($context['page'], $context['couponCode'], 'tabby', $user->id);

                $attempt = !empty($context['attempt_id']) ? PaymentAttempt::find($context['attempt_id']) : null;
                if ($attempt && (($totalData['total'] ?? 0) <= 0 || (empty($totalData['cart_ids']) && empty($totalData['gift_ids']) && empty($totalData['product_ids'])))) {
                    $totalData['total'] = (float) $attempt->amount;
                    $totalData['cart_ids'] = $attempt->cart_ids ?? [];
                    $totalData['gift_ids'] = $attempt->gift_ids ?? [];
                    $totalData['product_ids'] = $attempt->product_ids ?? [];
                    $totalData['discountAmount'] = (float) $attempt->discount_amount;
                }

                if (isset($totalData['error']) && ! $attempt) {
                    return $this->respondFailure($request, $totalData['error'], 422);
                }

                $fakeRequest = new Request([
                    'wallet'    => $context['wallet'],
                    'loyalty'   => $context['loyalty'],
                    'gift_code' => $context['gift_code'],
                    'gift_amount' => $context['gift_amount'],
                ]);
                $subResult = $subMethodService->apply($user->id, $fakeRequest, $totalData['total']);
                if (isset($subResult['error'])) {
                    $this->markPaymentAttemptFailed($context['attempt_id'] ?? null, $subResult['error'], [
                        'gateway_transaction_id' => $checkoutId,
                        'gateway_checkout_id' => $checkoutId,
                        'gateway_response' => $charge,
                        'callback_payload' => $request->all(),
                    ]);
                    return $this->respondFailure($request, $subResult['error'], 422);
                }
                $invoiceId = DB::transaction(function () use ($user, $fakeRequest, $totalData, $subResult, $checkoutId, $charge, $request, $context) {
                    return $this->commitFinalizedPayment($user->id, $fakeRequest, [
                        'final_before_sub' => $totalData['total'],
                        'tax' => $totalData['tax'],
                        'discountAmount' => $totalData['discountAmount'],
                        'couponDiscountAmount' => $totalData['couponDiscountAmount'] ?? 0,
                        'paymentGatewayDiscountAmount' => $totalData['paymentGatewayDiscountAmount'] ?? 0,
                        'paymentGatewayDiscountMethod' => $totalData['paymentGatewayDiscountMethod'] ?? null,
                        'paymentGatewayDiscountLabel' => $totalData['paymentGatewayDiscountLabel'] ?? null,
                        'page' => $context['page'],
                        'cart_ids' => $totalData['cart_ids'] ?? [],
                        'gift_ids' => $totalData['gift_ids'] ?? [],
                        'payment_method' => 'tabby',
                        'couponCode' => $context['couponCode'] ?? '',
                        'attempt_id' => $context['attempt_id'] ?? null,
                    ], $subResult, [
                        'attempt_id' => $context['attempt_id'] ?? null,
                        'transaction_id' => $checkoutId,
                        'merchant_reference' => data_get($charge, 'order.reference_id'),
                        'checkout_id' => $checkoutId,
                        'order_id' => data_get($charge, 'order.reference_id'),
                        'gateway_response' => $charge,
                        'callback_payload' => $request->all(),
                    ]);
                });
 
                return $this->respondSuccess($request, 'Payment captured successfully.', [
                    'invoice_id' => $invoiceId ?? null,
                ]);
            }
        }

        if ($this->isTabbyFailureStatus($normalizedStatus)) {
            $message = $this->resolveTabbyUnavailableMessage($charge);

            if (in_array($normalizedStatus, ['cancelled', 'canceled', 'expired'], true)) {
                $this->markPaymentAttemptCancelled($attemptId, $message, [
                    'gateway_transaction_id' => $checkoutId,
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $charge,
                    'callback_payload' => $request->all(),
                ]);
            } else {
                $this->markPaymentAttemptFailed($attemptId, $message, [
                    'gateway_transaction_id' => $checkoutId,
                    'gateway_checkout_id' => $checkoutId,
                    'gateway_response' => $charge,
                    'callback_payload' => $request->all(),
                ]);
            }

            session()->forget('tabby_payment');
            return $this->respondFailure($request, $message, 402);
        }

        $message = $normalizedStatus !== '' ? 'Tabby payment pending: ' . $normalizedStatus : 'Tabby payment pending';
        $this->markPaymentAttemptPending($attemptId, [
            'gateway_transaction_id' => $checkoutId,
            'gateway_checkout_id' => $checkoutId,
            'gateway_response' => $charge,
            'callback_payload' => $request->all(),
        ]);

        return $this->respondFailure($request, $message, 409);
    }

    public function fail(Request $request, $invoice = null)
    {
        $this->markPaymentAttemptFailed($this->resolveAttemptId($request, session('tabby_payment')), __('messages.payment_failed'), [
            'callback_payload' => $request->all(),
        ]);
        session()->forget('tabby_payment');
        return $this->respondFailure($request, __('messages.payment_failed'), 402);
    }

    public function cancel(Request $request, $invoice = null)
    {
        $this->markPaymentAttemptCancelled($this->resolveAttemptId($request, session('tabby_payment')), __('messages.payment_cancelled'), [
            'callback_payload' => $request->all(),
        ]);
        session()->forget('tabby_payment');
        return $this->respondFailure($request, __('messages.payment_cancelled'), 400);
    }

    private function buildSignedMerchantUrls(Request $request, string $typePage, array $data): array
    {
        $invoiceRef = $this->buildTabbyInvoiceReference($data);
        $params = array_filter([
            'invoice' => $invoiceRef,
            'attempt_id' => $data['attempt_id'] ?? null,
            'user_id' => $data['user_id'],
            'page' => $typePage,
            'coupon_code' => $data['couponCode'] ?? null,
            'wallet' => $request->boolean('wallet') ? 1 : null,
            'loyalty' => $request->boolean('loyalty') ? 1 : null,
            'gift_code' => $request->get('gift_code'),
            'gift_amount' => $request->get('gift_amount'),
            'discount_amount' => $request->get('discount_amount', $request->get('discountAmount')),
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        $successRoute = $this->wantsJson($request) ? 'api.tabby.success' : 'tabby.success';
        $failRoute = $this->wantsJson($request) ? 'api.tabby.fail' : 'tabby.fail';
        $cancelRoute = $this->wantsJson($request) ? 'api.tabby.cancel' : 'tabby.cancel';

        $successUrl = URL::temporarySignedRoute(
            $successRoute,
            now()->addMinutes(30),
            $params
        );

        return [
            "success" => $successUrl,
            "failure" => route($failRoute, ['invoice' => $params['invoice'] ?? null, 'attempt_id' => $data['attempt_id'] ?? null]),
            "cancel"  => route($cancelRoute, ['invoice' => $params['invoice'] ?? null, 'attempt_id' => $data['attempt_id'] ?? null]),
        ];
    }

    private function resolveStatelessContext(Request $request): ?array
    {
        if (! $request->hasValidSignatureWhileIgnoring(['checkout_id', 'checkoutId', 'payment_id', 'status', 'payment_status', 'order_id', 'id'])) {
            return null;
        }

        $userId = (int) $request->get('user_id');
        if ($userId <= 0) {
            
            return null;
        }

        $discountRaw = $request->get('discount_amount', $request->get('discountAmount'));
        $discount = null;
        if ($discountRaw !== null && $discountRaw !== '' && is_numeric($discountRaw)) {
            $discount = (float) $discountRaw;
        }

        return [
            'attempt_id' => (int) $request->get('attempt_id'),
            'user_id' => $userId,
            'page' => $request->get('page', 'cart'),
            'couponCode' => $request->get('coupon_code'),
            'wallet' => $request->boolean('wallet'),
            'loyalty' => $request->boolean('loyalty'),
            'gift_code' => $request->get('gift_code'),
            'gift_amount' => $request->get('gift_amount'),
            'discount_amount' => $discount,
        ];
    }

}
