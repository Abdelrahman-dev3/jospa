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
    }

    public function test_it_does_not_accept_plain_captured_query_as_authoritative(): void
    {
        $this->assertNull($this->resolveCallbackStatus(['result' => 'CAPTURED']));
    }

    public function test_it_rejects_not_captured_callback_as_failure(): void
    {
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'NOT CAPTURED']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'NOT APPROVED']));
        $this->assertSame('failure', $this->resolveCallbackStatus([], ['result' => 'VOIDED']));
    }

    public function test_it_does_not_treat_ambiguous_success_words_as_paid(): void
    {
        $this->assertNull($this->resolveCallbackStatus(['result' => 'success']));
        $this->assertNull($this->resolveCallbackStatus(['result' => 'APPROVED']));
        $this->assertNull($this->resolveCallbackStatus(['result' => 'AUTHORIZED']));
        $this->assertNull($this->resolveCallbackStatus(['responseMessage' => 'Payment successful']));
    }

    public function test_it_detects_cancelled_callbacks(): void
    {
        $this->assertSame('cancel', $this->resolveCallbackStatus(['result' => 'CANCELED']));
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
