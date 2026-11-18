<?php

namespace Verso\Plugins\Shop\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Verso\Plugins\Shop\Enums\OrderStatus;
use Verso\Plugins\Shop\Models\Order;

// Sent after an order moves to another status.
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public OrderStatus $from,
        public OrderStatus $to,
    ) {}
}
