<?php

namespace Tests\Unit\Services\Payment;

use App\Services\Currency\CurrencyService;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTOs\PaymentResult;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    protected PaymentService $paymentService;

    protected $mockProvider;

    protected $mockCurrencyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $this->mockCurrencyService = Mockery::mock(CurrencyService::class);

        $this->paymentService = new PaymentService(
            $this->mockProvider,
            $this->mockCurrencyService
        );
    }

    public function test_charge_payment_successfully(): void
    {
        $paymentData = [
            'amount' => 10000, // $100.00 in cents
            'currency' => 'usd',
            'description' => 'Test payment',
        ];

        $paymentResult = new PaymentResult(
            transactionId: 'txn_123',
            status: 'completed',
            provider: 'stripe',
            amount: 10000,
            currency: 'usd'
        );

        $this->mockCurrencyService
            ->shouldReceive('toSmallestUnit')
            ->once()
            ->with(10000, 'usd')
            ->andReturn(10000);

        $this->mockProvider
            ->shouldReceive('charge')
            ->once()
            ->andReturn($paymentResult);

        $result = $this->paymentService->charge($paymentData);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('txn_123', $result->transactionId);
        $this->assertEquals('completed', $result->status);
    }

    public function test_charge_handles_idempotency(): void
    {
        Cache::clear();

        $paymentData = [
            'amount' => 10000,
            'currency' => 'usd',
            'idempotency_key' => 'test-key-123',
        ];

        $paymentResult = new PaymentResult(
            transactionId: 'txn_123',
            status: 'completed',
            provider: 'stripe',
            amount: 10000,
            currency: 'usd'
        );

        $this->mockCurrencyService
            ->shouldReceive('toSmallestUnit')
            ->once()
            ->andReturn(10000);

        $this->mockProvider
            ->shouldReceive('charge')
            ->once()
            ->andReturn($paymentResult);

        // First call
        $result1 = $this->paymentService->charge($paymentData);

        // Second call with same idempotency key should return cached result
        $result2 = $this->paymentService->charge($paymentData);

        $this->assertEquals($result1->transactionId, $result2->transactionId);
    }

    public function test_refund_payment(): void
    {
        $refundResult = new PaymentResult(
            transactionId: 'txn_123',
            status: 'refunded',
            provider: 'stripe',
            amount: 10000,
            currency: 'usd'
        );

        $this->mockProvider
            ->shouldReceive('refund')
            ->once()
            ->with('txn_123', null)
            ->andReturn($refundResult);

        $result = $this->paymentService->refund('txn_123');

        $this->assertEquals('refunded', $result->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
