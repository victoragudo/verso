<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A heading from h1 to h6, with an optional anchor id.
class HeadingBlock extends BaseBlock
{
    // Anchors must look like a simple HTML id.
    public const string ANCHOR_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]*$/';

    public function name(): string
    {
        return 'heading';
    }

    public function rules(): array
    {
        return [
            'level' => ['nullable', 'integer', 'between:1,6'],
            'anchor' => ['nullable', 'string', 'max:100', 'regex:'.self::ANCHOR_PATTERN],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $level = max(1, min(6, $block->int('level', 2) ?? 2));
        $anchor = $block->string('anchor');
        $id = preg_match(self::ANCHOR_PATTERN, $anchor) === 1 ? $this->attribute('id', $anchor) : '';

        return "<h{$level}{$id}>".$renderer->sanitizeInline($block->content)."</h{$level}>";
    }
}
