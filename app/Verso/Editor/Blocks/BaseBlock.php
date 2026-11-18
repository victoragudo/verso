<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Contracts\Block;

// Shared defaults for block types: no attrs rules, no children and no special capability.
abstract class BaseBlock implements Block
{
    public function rules(): array
    {
        return [];
    }

    public function acceptsChild(string $type): bool
    {
        return false;
    }

    public function capability(): ?string
    {
        return null;
    }

    // A <figcaption> with sanitized inline HTML, or nothing when the caption is empty.
    protected function caption(string $caption, BlockRenderer $renderer): string
    {
        $caption = trim($renderer->sanitizeInline($caption));

        return $caption === '' ? '' : '<figcaption>'.$caption.'</figcaption>';
    }

    // An HTML attribute with an escaped value, or nothing when the value is null.
    protected function attribute(string $name, string|int|null $value): string
    {
        return $value === null ? '' : ' '.$name.'="'.e((string) $value).'"';
    }
}
