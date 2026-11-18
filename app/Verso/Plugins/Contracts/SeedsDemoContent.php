<?php

namespace App\Verso\Plugins\Contracts;

// A plugin provider that adds its own demo content after the core demo content is seeded.
interface SeedsDemoContent
{
    public function seedDemoContent(): void;
}
