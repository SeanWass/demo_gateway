<?php

namespace App\Services\Payments;

final class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $transactionId = '',
        public ?float $amount = null,
        public ?string $message = null,
        public array  $meta = []
    ) {}

    public static function success(
        string $transactionId = '',
        ?float $amount = null,
        array $meta = [],
        ?string $message = null
    ) : self
    {
        return new self(true, $transactionId, $amount, $message, $meta);
    }

    public static function failure(
        ?string $message = null,
        array $meta = []
    ): self
    {
        return new self(false, '', null, $message, $meta);
    }
}
