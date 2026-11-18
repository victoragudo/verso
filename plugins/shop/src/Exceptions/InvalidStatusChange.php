<?php

namespace Verso\Plugins\Shop\Exceptions;

use RuntimeException;
use Verso\Plugins\Shop\Enums\OrderStatus;

// Thrown when an order cannot move from its status to the wanted one.
class InvalidStatusChange extends RuntimeException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self(__('shop::An order that is ":from" cannot become ":to".', ['from' => $from->label(), 'to' => $to->label()]));
    }
}
