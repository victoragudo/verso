<?php

namespace App\Verso\Extensions\Exceptions;

use RuntimeException;

// Thrown when an uploaded package waiting for consent is missing or expired.
class StagedPackageNotFound extends RuntimeException
{
    public static function token(string $token): self
    {
        return new self(__('The uploaded package ":token" was not found or has expired.', ['token' => $token]));
    }
}
