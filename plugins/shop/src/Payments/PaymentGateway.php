<?php

namespace Verso\Plugins\Shop\Payments;

use Verso\Plugins\Shop\Models\Order;

// A way to pay for an order. Other plugins can add their own through PaymentGateways::register().
interface PaymentGateway
{
    // Short id stored on orders, like "bank_transfer".
    public function key(): string;

    // Name shown at the checkout.
    public function label(): string;

    // One line shown under the name at the checkout.
    public function description(): string;

    // False when the gateway is turned off or not set up.
    public function isAvailable(): bool;

    // Start paying a new order: send the customer somewhere, or finish at once.
    public function start(Order $order): PaymentStart;

    // Text shown on the order page and in emails while the order waits for payment. Empty for none.
    public function instructions(Order $order): string;
}
