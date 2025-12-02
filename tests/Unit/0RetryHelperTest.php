<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\RetryHelper;
use App\Models\PaymentRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Exception;

class RetryHelperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_success_without_retry()
    {
        $callback = function ($attempt) {
            return "ok";
        };

        $result = RetryHelper::run(
            $callback,
            [],
            1,
            'test'
        );

        $this->assertEquals("ok", $result);
        $this->assertEquals(0, PaymentRetry::count());
    }

    /** @test */
    public function it_retries_when_status_code_matches_strategy()
    {
        Log::shouldReceive('warning')->times(1);

        $attempts = 0;

        $callback = function ($attempt) use (&$attempts) {
            $attempts++;

            if ($attempt < 2) {
                // Throw exception with HTTP 429
                throw new Exception("Rate limit", 429);
            }

            return "success";
        };

        $strategies = [
            [
                'status_codes' => [429],
                'type' => 'exponential',
                'base_delay_ms' => 1,
                'max_attempts' => 3,
            ]
        ];

        $result = RetryHelper::run($callback, $strategies, 10, 'authorise');

        $this->assertEquals("success", $result);
        $this->assertEquals(1, PaymentRetry::count()); // First failure logged
    }

    /** @test */
    public function it_throws_exception_when_no_strategy_matches()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Some error");

        $callback = function () {
            throw new Exception("Some error", 400);
        };

        $strategies = [
            [
                'status_codes' => [429],
                'max_attempts' => 3,
                'type' => 'exponential'
            ]
        ];

        RetryHelper::run($callback, $strategies, 5, 'op');

        $this->assertEquals(0, PaymentRetry::count());
    }

    /** @test */
    public function it_throws_after_final_attempt_and_logs_retry_record()
    {
        Log::shouldReceive('warning')->times(2); // two retries

        $callback = function ($attempt) {
            throw new Exception("Fail", 429);
        };

        $strategies = [
            [
                'status_codes' => [429],
                'max_attempts' => 3,
                'type' => 'exponential',
                'base_delay_ms' => 1,
            ]
        ];

        $this->expectException(Exception::class);

        try {
            RetryHelper::run($callback, $strategies, 99, 'refund');
        } finally {
            // Should have 3 rows: attempt 1, attempt 2, attempt 3 (final)
            $this->assertEquals(3, PaymentRetry::count());

            $this->assertDatabaseHas('payment_retries', [
                'payment_id' => 99,
                'operation' => 'refund',
                'attempt' => 3,
                'exception_message' => 'Fail'
            ]);
        }
    }

    /** @test */
    public function it_calculates_exponential_delay()
    {
        $strategy = [
            'type' => 'exponential',
            'base_delay_ms' => 100,
        ];

        $d1 = $this->invokeCalculateDelay(1, $strategy);
        $d2 = $this->invokeCalculateDelay(2, $strategy);
        $d3 = $this->invokeCalculateDelay(3, $strategy);

        $this->assertEquals(100, $d1);
        $this->assertEquals(200, $d2);
        $this->assertEquals(400, $d3);
    }

    /** @test */
    public function it_calculates_capped_exponential_delay()
    {
        $strategy = [
            'type' => 'capped_exponential',
            'base_delay_ms' => 100,
            'max_delay_ms' => 250
        ];

        $d1 = $this->invokeCalculateDelay(1, $strategy); //100
        $d2 = $this->invokeCalculateDelay(2, $strategy); //200
        $d3 = $this->invokeCalculateDelay(3, $strategy); //400 → capped to 250

        $this->assertEquals(100, $d1);
        $this->assertEquals(200, $d2);
        $this->assertEquals(250, $d3);
    }

    /**
     * Helper to call protected calculateDelay()
     */
    private function invokeCalculateDelay($attempt, $strategy)
    {
        return (new \ReflectionClass(RetryHelper::class))
            ->getMethod('calculateDelay')
            ->invoke(null, $attempt, $strategy);
    }
}
