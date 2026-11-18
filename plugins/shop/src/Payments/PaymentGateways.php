<?php

namespace Verso\Plugins\Shop\Payments;

// All payment gateways, in the order they are shown at the checkout.
class PaymentGateways
{
    /** @var array<string, PaymentGateway> */
    protected array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->key()] = $gateway;
    }

    public function find(string $key): ?PaymentGateway
    {
        return $this->gateways[$key] ?? null;
    }

    // A gateway that is turned on and set up, or null.
    public function available(string $key): ?PaymentGateway
    {
        $gateway = $this->find($key);

        return $gateway !== null && $gateway->isAvailable() ? $gateway : null;
    }

    /** @return list<PaymentGateway> */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /** @return list<PaymentGateway> */
    public function enabled(): array
    {
        return array_values(array_filter($this->gateways, fn (PaymentGateway $gateway): bool => $gateway->isAvailable()));
    }

    // Name of a gateway for lists, or its key when it is gone.
    public function label(string $key): string
    {
        return $this->find($key)?->label() ?? $key;
    }
}
