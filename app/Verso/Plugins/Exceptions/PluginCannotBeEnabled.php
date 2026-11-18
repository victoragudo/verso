<?php

namespace App\Verso\Plugins\Exceptions;

use RuntimeException;

// Thrown when a plugin is invalid or does not work with this Verso version.
class PluginCannotBeEnabled extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(string $slug, array $problems): self
    {
        return new self(__('The plugin ":slug" cannot be enabled: :problems', ['slug' => $slug, 'problems' => implode(' ', $problems)]));
    }
}
