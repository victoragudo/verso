<?php

namespace App\Verso\Easy\Starters;

// One ready section on a starter page: the section key and its content.
final readonly class StarterSection
{
    /**
     * @param  array<string, mixed>  $content  heading, text, items, images, button and extra. Images and item images name a StarterImage.
     */
    public function __construct(
        public string $key,
        public array $content = [],
    ) {}
}
