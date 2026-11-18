<?php

namespace App\Verso\Extensions\Exceptions;

use RuntimeException;

// Thrown when an uploaded zip is not a safe and valid plugin or theme package.
class InvalidPackage extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(__('The package was refused: :errors', ['errors' => implode(' ', $errors)]));
    }

    /**
     * @param  list<string>|string  $errors
     */
    public static function because(array|string $errors): self
    {
        return new self(is_string($errors) ? [$errors] : $errors);
    }
}
