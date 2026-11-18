<?php

namespace App\Verso\Install\Env;

use InvalidArgumentException;

// Thrown when a key or value cannot be stored safely in an env file.
class InvalidEnvValue extends InvalidArgumentException
{
    public static function key(string $key): self
    {
        return new self(__('":key" is not a valid environment key.', ['key' => $key]));
    }

    public static function lineBreak(string $key): self
    {
        return new self(__('The value of :key cannot contain a line break.', ['key' => $key]));
    }
}
