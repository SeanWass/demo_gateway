<?php

namespace App\Support;

use Closure;

interface IdempotencyInterface {
    public function run(Closure $callback, string $key, string $operation);
}
