<?php

namespace App\Support;

use Exception;
use App\Models\PaymentRetry;
use Illuminate\Support\Facades\Log;

class RetryHelper
{
    public static function run(
        callable $callback,
        array $strategies,
        ?int $paymentId = null,
        string $operation = ""
    ){
        $attempt = 0;

        beginning:

        try {
            $attempt++;
            return $callback($attempt);

        } catch (Exception $e) {

            $strategy = self::matchStrategy($e, $strategies);

            // If no strategy is found, throw exception.
            if (!$strategy) {
                throw $e;
            }

            // If final attempt, throw exception.
            if ($attempt >= ($strategy['max_attempts'])) {
                // Log final attempt.
                PaymentRetry::create([
                    'payment_id' => $paymentId,
                    'attempt' => $attempt,
                    'exception_type' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'operation' =>$operation
                ]);

                throw $e;
            }

            PaymentRetry::create([
                'payment_id' => $paymentId,
                'attempt' => $attempt,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'operation' =>$operation
            ]);

            // Exponential backoff
            $delay = self::calculateDelay($attempt, $strategy);

            Log::warning("Retryable exception caught. Attempt $attempt. Retrying in {$delay}ms. Error: {$e->getMessage()}");
            usleep($delay * 1000);

            goto beginning;
        }
    }

    protected static function matchStrategy(Exception $e, array $strategies)
    {
        foreach ($strategies as $strategy) {

            // Match HTTP status code (if using Http client)
            if (isset($strategy['status_codes']) && method_exists($e, 'getCode')) {
                if (in_array($e->getCode(), $strategy['status_codes'])) {
                    return $strategy;
                }
            }
        }

        return null;
    }

    protected static function calculateDelay(int $attempt, array $strategy)
    {
        $base = $strategy['base_delay_ms'] ?? 500;

        switch ($strategy['type'] ?? 'exponential') {

            case 'fixed':
                return $base;

            case 'exponential':
                return $base * (2 ** ($attempt - 1));

            case 'jitter':
                return rand($base, $base * 3);

            case 'capped_exponential':
                $delay = $base * (2 ** ($attempt - 1));
                $max   = $strategy['max_delay_ms'] ?? 5000;
                return min($delay, $max);

            default:
                return $base; // fallback
        }
    }

}
