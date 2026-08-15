<?php

namespace Tests\Unit;

use App\Services\Payment\Strategies\UrPayPaymentStrategy;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class UrPayPaymentStatusTest extends TestCase
{
    public function test_it_accepts_decrypted_captured_payload_as_success(): void
    {
        $this->assertSame('success', $this->resolveCallbackStatus([], ['result' => 'CAPTURED']));
        $this->assertSame('success', $this->resolveCallbackStatus([], ['result' => 'APPROVED']));
        $this->assertSame('success', $this->resolveCallbackStatus([], ['result' => 'SUCCESS']));
        $this->assertSame('success', $this->resolveCallbackStatus([], ['result' => 'PAID']));
        $this->assertSame('success', $this->resolveCallbackStatus([], ['result' => 'SETTLED']));
    }

    public function test_it_does_not_accept_plain_captured_query_as_authoritative(): void
    {
        $this->assertNull($this->resolveCallbackStatus(['result' => 'CAPTURED']));
        $this->assertNull($this->resolveCallbackStatus(['result' => 'APPROVED']));
        $this->assertNull($this->resolveCallbackStatus(['result' => 'success']));
        $this->assertNull($this->resolveCallbackStatus(['responseMessage' => 'Payment successful']));
    }

    public function test_it_rejects_not_captured_callback_as_failure(): void
    {
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'NOT CAPTURED']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'NOT APPROVED']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'VOIDED']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'DECLINED']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'UNSUCCESSFUL']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'DENIED BY RISK']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'HOST TIMEOUT']));
    }

    public function test_it_detects_cancelled_callbacks(): void
    {
        $this->assertSame('cancel', $this->resolveCallbackStatus(['result' => 'CANCELED']));
        $this->assertSame('cancel', $this->resolveCallbackStatus([], ['result' => 'CANCELED']));
        $this->assertSame('cancel', $this->resolveCallbackStatus([], ['result' => 'CANCEL']));
    }

    public function test_it_resolves_paid_amount_from_payload(): void
    {
        $method = new ReflectionMethod(UrPayPaymentStrategy::class, 'resolveUrPayPaidAmount');
        $method->setAccessible(true);
        $strategy = new UrPayPaymentStrategy();

        $this->assertSame(150.75, $method->invoke($strategy, ['amt' => '150.75']));
        $this->assertSame(200.0, $method->invoke($strategy, ['amount' => '200.00']));
        $this->assertSame(500.0, $method->invoke($strategy, ['Amt' => '500']));
        $this->assertSame(0.0, $method->invoke($strategy, []));
        $this->assertSame(0.0, $method->invoke($strategy, null));
    }

    private function resolveCallbackStatus(array $query, ?array $decryptedPayload = null): ?string
    {
        $method = new ReflectionMethod(UrPayPaymentStrategy::class, 'resolveHostedCallbackStatus');
        $method->setAccessible(true);

        $request = Request::create('/urpay/success', 'GET', $query);
        if ($decryptedPayload !== null) {
            $request->attributes->set('urpay_decrypted_payload', $decryptedPayload);
        }

        return $method->invoke(new UrPayPaymentStrategy(), $request);
    }
}
