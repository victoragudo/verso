<?php

namespace App\Verso\Plugins\Exceptions;

use App\Verso\Plugins\Contracts\Filter;
use InvalidArgumentException;

// Thrown when a class used as a filter does not implement the Filter contract.
class UnknownFilter extends InvalidArgumentException
{
    public static function for(string $filter): self
    {
        return new self("{$filter} is not a filter. Filter classes must implement ".Filter::class.'.');
    }
}
