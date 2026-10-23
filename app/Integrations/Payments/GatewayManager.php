<?php

namespace App\Integrations\Payments;

use App\Models\Estate;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class GatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private array $gateways = [
        'payaza' => PayazaGateway::class,
        'paystack' => PaystackGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function get(string $name): PaymentGateway
    {
        if (! isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
        }

        return $this->container->make($this->gateways[$name]);
    }

    /**
     * Gateways that are configured on the platform and enabled for the estate.
     *
     * @return array<string, PaymentGateway>
     */
    public function availableFor(Estate $estate): array
    {
        $allowed = (array) $estate->setting('power.gateways');

        return collect($this->gateways)
            ->keys()
            ->filter(fn (string $name) => in_array($name, $allowed, true))
            ->mapWithKeys(fn (string $name) => [$name => $this->get($name)])
            ->filter(fn (PaymentGateway $gateway) => $gateway->isAvailable())
            ->all();
    }
}
