<?php

namespace App\Verso\Plugins\Contracts;

// Optional extras for a plugin block with fields. Without this interface the block still gets its shortcode.
interface EmbeddableBlock
{
    // False turns off the shortcode and the Blade component for this block.
    public function shortcode(): bool;

    // The attr that names the embedded item, like "form" or "product". Used by the usage index.
    public function keyAttribute(): ?string;

    /**
     * Other names for attrs in a shortcode, like ['slug' => 'form'] for [form slug="contact"].
     *
     * @return array<string, string>
     */
    public function attributeAliases(): array;

    // Capability a user needs to add this block or shortcode, or null when anyone can.
    public function capability(): ?string;
}
