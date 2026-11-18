<?php

namespace App\Verso\Plugins\Exceptions;

use RuntimeException;

// Thrown when no plugin has the given slug.
class PluginNotFound extends RuntimeException
{
    public static function slug(string $slug): self
    {
        return new self("No plugin with the slug \"{$slug}\" was found.");
    }
}
