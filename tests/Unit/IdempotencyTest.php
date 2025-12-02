<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\IdempotencyKey;
use App\Support\IdempotencyInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_runs_the_callback_and_stores_result_when_key_is_new()
    {
        $service = $this->app->make(IdempotencyInterface::class);

        $result = $service->run(
            callback: fn() => 'FIRST_RESULT',
            key: 'test-key-123',
            operation: 'authorise'
        );

        $this->assertEquals('FIRST_RESULT', $result);

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'test-key-123',
            'operation' => 'authorise',
            'response' => "\"FIRST_RESULT\"",
        ]);
    }

    /** @test */
    public function it_returns_existing_response_without_running_callback_again()
    {
        // create existing stored key
        IdempotencyKey::create([
            'key'       => 'test-key-123',
            'operation' => 'authorise',
            'response'  => 'STORED_RESULT',
        ]);

        $service = $this->app->make(IdempotencyInterface::class);

        $callbackCalled = false;

        $result = $service->run(
            callback: function () use (&$callbackCalled) {
                $callbackCalled = true;
                return 'NEW_RESULT';
            },
            key: 'test-key-123',
            operation: 'authorise'
        );

        $this->assertEquals('STORED_RESULT', $result);

        // callback should NEVER be executed
        $this->assertFalse($callbackCalled);
    }

    /** @test */
    public function it_stores_in_progress_record_before_running_callback()
    {
        $service = $this->app->make(IdempotencyInterface::class);

        $service->run(
            callback: fn() => 'DONE',
            key: 'key-789',
            operation: 'refund'
        );

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'key-789',
            'operation' => 'refund',
            'response' => "\"DONE\"",
        ]);
    }
}
