<?php

namespace Tests\Feature\PaymentRequests;

use App\Models\PaymentRequestAttempt;
use App\Services\HyperpayService;

/** In-memory stand-in for HyperpayService. Never calls the network, never charges. */
class FakeHyperpay extends HyperpayService
{
    public array $checkouts = [];

    public array $lookups = [];

    public bool $failCheckout = false;

    /** checkout id => payment-status response */
    private array $results = [];

    public function createCheckout(float $amount, string $merchantTransactionId, string $shopperResultUrl, array $customer = [], string $brand = 'VISA'): array
    {
        if ($this->failCheckout) {
            throw new \RuntimeException('Hyperpay request failed.');
        }

        $id = 'CHK'.(count($this->checkouts) + 1).strtoupper(substr(md5($merchantTransactionId), 0, 8));
        $this->checkouts[] = compact('amount', 'merchantTransactionId', 'shopperResultUrl', 'brand', 'id');

        return ['id' => $id, 'result' => ['code' => '000.200.100', 'description' => 'successfully created checkout']];
    }

    public function fetchPaymentStatus(string $resourcePath, string $brand = 'VISA'): array
    {
        $this->lookups[] = $resourcePath;
        $checkoutId = rawurldecode(explode('/', trim($resourcePath, '/'))[2] ?? '');

        if (! isset($this->results[$checkoutId])) {
            throw new \RuntimeException('No payment session found for the requested id');
        }

        return $this->results[$checkoutId];
    }

    public function widgetScriptUrl(string $checkoutId, string $brand = 'VISA'): string
    {
        return 'https://example.test/v1/paymentWidgets.js?checkoutId='.$checkoutId;
    }

    /** Simulate a captured payment (includes card fields the real gateway returns). */
    public function paid(PaymentRequestAttempt $attempt, ?string $amount = null): void
    {
        $this->results[$attempt->checkout_id] = [
            'id' => 'PAY-'.$attempt->checkout_id,
            'paymentType' => 'DB',
            'paymentBrand' => $attempt->brand,
            'amount' => $amount ?? number_format((float) $attempt->amount, 2, '.', ''),
            'currency' => 'SAR',
            'merchantTransactionId' => $attempt->merchant_transaction_id,
            'result' => ['code' => '000.000.000', 'description' => 'Transaction succeeded'],
            'card' => ['bin' => '411111', 'last4Digits' => '1111', 'holder' => 'Jane Jones', 'expiryMonth' => '05', 'expiryYear' => '2034'],
            'customer' => ['givenName' => 'Test'],
        ];
    }

    public function result(PaymentRequestAttempt $attempt, string $code, string $description, bool $withPayment = false): void
    {
        $this->results[$attempt->checkout_id] = array_filter([
            'id' => $withPayment ? 'PAY-'.$attempt->checkout_id : null,
            'amount' => $withPayment ? number_format((float) $attempt->amount, 2, '.', '') : null,
            'currency' => 'SAR',
            'merchantTransactionId' => $attempt->merchant_transaction_id,
            'result' => ['code' => $code, 'description' => $description],
        ]);
    }
}
