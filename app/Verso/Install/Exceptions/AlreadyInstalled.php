<?php

namespace App\Verso\Install\Exceptions;

use RuntimeException;

// Thrown when someone tries to install Verso a second time.
class AlreadyInstalled extends RuntimeException
{
    public static function make(): self
    {
        return new self('Verso is already installed.');
    }
}
