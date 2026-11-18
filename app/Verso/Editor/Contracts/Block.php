<?php

namespace App\Verso\Editor\Contracts;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A block type: its name, how to validate it and how to turn it into HTML.
interface Block
{
    // Unique type name stored in the block JSON, like "paragraph".
    public function name(): string;

    /**
     * Laravel validation rules for the block attrs.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    // True when a block of this type can be a child of this block.
    public function acceptsChild(string $type): bool;

    // Capability a user needs to save this block, or null when anyone can.
    public function capability(): ?string;

    // Returns safe HTML for the block.
    public function render(BlockData $block, BlockRenderer $renderer): string;
}
