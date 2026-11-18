<?php

namespace Verso\Plugins\Shop\Exceptions;

use RuntimeException;

// Thrown when fewer items are in stock than people want to buy.
class OutOfStock extends RuntimeException
{
    public static function for(string $name, int $available): self
    {
        return new self($available > 0
            ? __('shop::Only :count of ":name" left in stock.', ['count' => $available, 'name' => $name])
            : __('shop::":name" is out of stock.', ['name' => $name]));
    }
}
