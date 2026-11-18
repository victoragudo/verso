<?php

namespace App\Verso\Access\Permissions;

// The capabilities one user has right now. Administrators have every capability.
final readonly class AccessSet
{
    /**
     * @param  array<string, true>  $capabilities
     */
    public function __construct(
        public array $capabilities,
        public bool $all = false,
    ) {}

    public function allows(string $capability): bool
    {
        return $this->all || isset($this->capabilities[$capability]);
    }
}
