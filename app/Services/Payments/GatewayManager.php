<?php
namespace App\Services\Payments;

use Psr\Container\ContainerInterface;
use Illuminate\Contracts\Container\BindingResolutionException;

class GatewayManager
{
    protected ContainerInterface $container;
    protected array $map; // map: ['stripe' => StripeGateway::class, 'payfast' => PayfastGateway::class]

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->map = config('payment.gateways') ?? [];
    }

    /**
     * Resolve by name (throws if not configured)
     */
    public function gateway(string $name): PaymentGatewayInterface
    {
        if (! isset($this->map[$name])) {
            throw new \InvalidArgumentException("Payment gateway [{$name}] is not configured");
        }

        return $this->container->make($this->map[$name]);
    }

    /**
     * Get default gateway name from config
     */
    public function default(): PaymentGatewayInterface
    {
        $name = config('payment.default_gateway');
        return $this->gateway($name);
    }
}
