<?php

namespace App\Providers;

use App\Support\Idempotency;
use App\Support\IdempotencyInterface;
use Illuminate\Support\ServiceProvider;

class IdempotencyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(IdempotencyInterface::class, Idempotency::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
