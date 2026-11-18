<?php

namespace Verso\Plugins\Shop\Exceptions;

use RuntimeException;

// Thrown when someone tries to check out without products in the cart.
class EmptyCart extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('shop::Your cart is empty.'));
    }
}
