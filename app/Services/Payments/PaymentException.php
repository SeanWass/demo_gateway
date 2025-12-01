<?php
namespace App\Services\Payments;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class PaymentException extends RequestException
{
    protected array $meta;

    /**
     * @param string $message
     * @param \Illuminate\Http\Client\Response|null $response
     * @param array $meta
     */
    public function __construct(
        ?Response $response = null,
        string $message = "",
        array $meta = []
    ) {
        parent::__construct($response, $message);
        $this->meta = $meta;
    }

    /**
     * Optional meta info for debugging (gateway payload, etc)
     */
    public function meta(): array
    {
        return $this->meta;
    }
}

