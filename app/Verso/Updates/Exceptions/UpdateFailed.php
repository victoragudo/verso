<?php

namespace App\Verso\Updates\Exceptions;

use RuntimeException;

// An update step failed. The message is safe to show to admins.
class UpdateFailed extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
