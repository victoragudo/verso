<?php

namespace App\Verso\Extensions\Exceptions;

use RuntimeException;

// Thrown when a package cannot be installed, updated or removed.
class ExtensionInstallFailed extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
