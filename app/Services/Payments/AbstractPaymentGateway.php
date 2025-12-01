<?php
namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Factory as HttpFactory;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    protected HttpFactory $http;

    public function __construct(HttpFactory $http)
    {
        $this->http = $http;
    }

    /**
     * Optional helper to normalise amounts
     */
    protected function normaliseAmount(float $amount): float
    {
        return round($amount, 2);
    }

    /**
     * Default error handler/to-exception wrapper
     */
    protected function handleError(
        ?Response $response,
        string $message,
        array $meta = []
    ): void
    {
        Log::error('Payment gateway error: '.$message, $meta);
        throw new PaymentException($response, $message, $meta);
    }
}
