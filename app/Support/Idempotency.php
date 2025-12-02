<?php

namespace App\Support;

use Closure;
use App\Models\IdempotencyKey;
use App\Support\IdempotencyInterface;
class Idempotency implements IdempotencyInterface
{
    public function run(Closure $callback, string $key, string $operation)
    {
        // Check if action already executed
        $existing = IdempotencyKey::where('key', $key)->first();

        if ($existing) {
            // Return previously stored response
            return $existing->response;
        }

        // Store "in-progress" to prevent race conditions
        $record = IdempotencyKey::create([
            'key'       => $key,
            'operation' => $operation,
            'response'  => null,
        ]);

        // Run actual operation
        $result = $callback();

        // Save successful result
        $record->update([
            'response' => $result,
        ]);

        return $result;
    }
}
