<?php

namespace App\Verso\Theme\AdminBar;

// One link of the admin bar: the text people see and where it goes.
final readonly class AdminBarLink
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
