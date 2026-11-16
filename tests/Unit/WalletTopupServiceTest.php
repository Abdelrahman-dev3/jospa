<?php

namespace Tests\Unit;

use Modules\Wallet\Models\WalletTopupAttempt;
use Modules\Wallet\Services\WalletTopupService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class WalletTopupServiceTest extends TestCase
{
    public static function resultCodes(): array
    {
        return [
            'live success' => ['000.000.000', false, 'paid'],
            'live success 3ds' => ['000.000.100', false, 'paid'],
            'success needs review' => ['000.400.110', false, 'paid'],
            'test-mode success on live gateway' => ['000.100.110', false, 'failed'],
            'test-mode success on test gateway' => ['000.100.110', true, 'paid'],
            'pending' => ['000.200.000', false, 'pending'],
            'pending long' => ['800.400.500', false, 'pending'],
            'no code yet' => ['', false, 'pending'],
            'cancelled by user' => ['100.396.101', false, 'cancelled'],
            'declined' => ['800.100.151', false, 'failed'],
            '3ds failed' => ['100.390.112', false, 'failed'],
        ];
    }

    #[DataProvider('resultCodes')]
    public function test_classifies_hyperpay_result_codes(string $code, bool $testGateway, string $expected): void
    {
        $this->assertSame($expected, WalletTopupService::classifyResultCode($code, $testGateway));
    }

    public function test_normalizes_brands(): void
    {
        $this->assertSame('MADA', WalletTopupService::normalizeBrand('mada'));
        $this->assertSame('APPLEPAY', WalletTopupService::normalizeBrand('apple_pay'));
        $this->assertSame('VISA', WalletTopupService::normalizeBrand('MASTER'));
        $this->assertSame('VISA', WalletTopupService::normalizeBrand('anything'));
    }

    public function test_accepts_payment_that_matches_attempt(): void
    {
        $this->assertNull($this->mismatch(['id' => 'p1', 'amount' => '150.00', 'currency' => 'SAR', 'merchantTransactionId' => 'ATESTMTID0000001']));
    }

    public function test_rejects_amount_mismatch(): void
    {
        $this->assertNotNull($this->mismatch(['id' => 'p1', 'amount' => '1.00', 'currency' => 'SAR', 'merchantTransactionId' => 'ATESTMTID0000001']));
    }

    public function test_rejects_missing_amount(): void
    {
        $this->assertNotNull($this->mismatch(['id' => 'p1', 'currency' => 'SAR', 'merchantTransactionId' => 'ATESTMTID0000001']));
    }

    public function test_rejects_other_merchant_transaction(): void
    {
        $this->assertNotNull($this->mismatch(['id' => 'p1', 'amount' => '150.00', 'currency' => 'SAR', 'merchantTransactionId' => 'SOMEONEELSE00001']));
    }

    public function test_rejects_other_currency(): void
    {
        $this->assertNotNull($this->mismatch(['id' => 'p1', 'amount' => '150.00', 'currency' => 'USD', 'merchantTransactionId' => 'ATESTMTID0000001']));
    }

    public function test_rejects_missing_gateway_id(): void
    {
        $this->assertNotNull($this->mismatch(['amount' => '150.00', 'currency' => 'SAR', 'merchantTransactionId' => 'ATESTMTID0000001']));
    }

    public function test_idempotent_replay_matches_same_amount_and_brand(): void
    {
        $attempt = new WalletTopupAttempt();
        $attempt->setRawAttributes(['amount' => '150.00', 'brand' => 'MADA']);

        $this->assertTrue(WalletTopupService::matchesRequest($attempt, 150, 'mada'));
        $this->assertTrue(WalletTopupService::matchesRequest($attempt, 150.004, 'MADA'));
        $this->assertFalse(WalletTopupService::matchesRequest($attempt, 151, 'MADA'));
        $this->assertFalse(WalletTopupService::matchesRequest($attempt, 150, 'VISA'));
    }

    private function mismatch(array $payment): ?string
    {
        $attempt = new WalletTopupAttempt();
        $attempt->setRawAttributes([
            'amount' => '150.00',
            'merchant_transaction_id' => 'ATESTMTID0000001',
        ]);

        $service = (new ReflectionClass(WalletTopupService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'paymentMismatch');
        $method->setAccessible(true);

        return $method->invoke($service, $attempt, $payment);
    }
}
