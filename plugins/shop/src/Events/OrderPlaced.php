<?php

namespace Verso\Plugins\Shop\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Verso\Plugins\Shop\Models\Order;

// Sent after a customer places an order at the checkout.
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
