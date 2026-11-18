<?php

namespace App\Verso\Plugins\Exceptions;

use RuntimeException;

// Thrown when a plugin manifest is missing, unreadable or has wrong values.
class InvalidManifest extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly string $path, public readonly array $errors)
    {
        parent::__construct('Invalid plugin manifest at '.$path.': '.implode(' ', $errors));
    }
}
