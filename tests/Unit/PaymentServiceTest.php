<?php

namespace Tests\Unit;

use Mockery;
use App\Services\Payments\PaymentResult;
use Tests\TestCase;
use App\Models\Refund;
use App\Models\Payment;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\PaymentService;
use App\Support\IdempotencyInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $service;
    protected $gatewayManagerMock;
    protected $gatewayMock;
    protected $idempotencyMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock gateway + manager
        $this->gatewayMock = Mockery::mock(\App\Services\Payments\PaymentGatewayInterface::class);
        $this->gatewayManagerMock = Mockery::mock(GatewayManager::class);

        // Mock IdempotencyInterface
        $this->idempotencyMock = Mockery::mock(IdempotencyInterface::class);

        $this->service = new PaymentService(
            $this->gatewayManagerMock,
            $this->idempotencyMock
        );
    }

    /** @test */
    public function it_creates_and_authorises_a_payment_successfully()
    {
        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->with('example')
            ->andReturn($this->gatewayMock);

        $result = new PaymentResult(
            success: true,
            transactionId: 'txn_123',
            meta: ['foo' => 'bar']
        );

        // Mock idempotency to run callback and return our fake result
        $this->idempotencyMock
            ->shouldReceive('run')
            ->andReturnUsing(fn ($callback) => $callback());

        // Mock RetryHelper to return the PaymentResult
        \Mockery::mock('alias:App\Support\RetryHelper')
            ->shouldReceive('run')
            ->andReturn($result);

        $payment = $this->service->createAndAuthorise(
            'example',
            100.00,
            'ZAR',
            'tok_abc'
        );

        $this->assertEquals('authorised', $payment->status);
        $this->assertEquals('txn_123', $payment->gateway_txn_id);
        $this->assertEquals(['foo' => 'bar'], $payment->gateway_response);
    }

    /** @test */
    public function it_records_failed_authorise_attempt()
    {
        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->with('example')
            ->andReturn($this->gatewayMock);

        $result = new PaymentResult(
            success: false,
            transactionId: 1,
            meta: ['error' => 'fail'],
            message: 'failed'
        );

        $this->idempotencyMock
            ->shouldReceive('run')
            ->andReturnUsing(fn ($callback) => $callback());

        \Mockery::mock('alias:App\Support\RetryHelper')
            ->shouldReceive('run')
            ->andReturn($result);

        $payment = $this->service->createAndAuthorise(
            'example',
            100,
            'ZAR',
            'tok'
        );

        $this->assertEquals('failed', $payment->status);
    }

    /** @test */
    public function it_captures_a_payment()
    {
        $payment = Payment::factory()->create([
            'gateway' => 'example',
            'amount' => 200,
            'status' => 'authorised'
        ]);

        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->andReturn($this->gatewayMock);

        $result = new PaymentResult(
            success: true,
            transactionId: 'cap_555',
            meta: ['ok' => true]
        );

        $this->idempotencyMock
            ->shouldReceive('run')
            ->andReturnUsing(fn ($callback) => $callback());

        \Mockery::mock('alias:App\Support\RetryHelper')
            ->shouldReceive('run')
            ->andReturn($result);

        $payment = $this->service->capture($payment);

        $this->assertEquals('captured', $payment->status);
    }

    /** @test */
    public function it_voids_a_payment()
    {
        $payment = Payment::factory()->create([
            'gateway' => 'example',
            'status' => 'authorised'
        ]);

        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->andReturn($this->gatewayMock);

        $result = new PaymentResult(true, 'void_123', 123.00);

        $this->idempotencyMock
            ->shouldReceive('run')
            ->andReturnUsing(fn ($callback) => $callback());

        \Mockery::mock('alias:App\Support\RetryHelper')
            ->shouldReceive('run')
            ->andReturn($result);

        $payment = $this->service->void($payment);

        $this->assertEquals('voided', $payment->status);
    }

    /** @test */
    public function it_creates_refund_record_on_refund()
    {
        $payment = Payment::factory()->create([
            'gateway' => 'example',
            'amount' => 500,
            'status' => 'captured',
            'gateway_txn_id' => 'txn_abc'
        ]);

        $this->gatewayManagerMock
            ->shouldReceive('gateway')
            ->andReturn($this->gatewayMock);

        $result = new PaymentResult(true, 'refund_777', 123.00);

        $this->idempotencyMock
            ->shouldReceive('run')
            ->andReturnUsing(fn ($callback) => $callback());

        \Mockery::mock('alias:App\Support\RetryHelper')
            ->shouldReceive('run')
            ->andReturn($result);

        $updated = $this->service->refund($payment, 100, 'test');

        $this->assertEquals('refunded', $updated->status);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'amount' => 100,
            'gateway_refund_id' => 'refund_777',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
