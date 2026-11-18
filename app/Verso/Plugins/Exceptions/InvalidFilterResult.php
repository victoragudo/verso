<?php

namespace App\Verso\Plugins\Exceptions;

use RuntimeException;

// Thrown when a filter callback returns a value of the wrong type.
class InvalidFilterResult extends RuntimeException
{
    public static function for(string $filter, string $expected, string $actual): self
    {
        return new self("A callback of {$filter} returned {$actual}, but {$expected} was expected.");
    }
}
