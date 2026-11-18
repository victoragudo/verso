<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A bulleted or numbered list. The content holds <li> items, which can hold nested lists.
class ListBlock extends BaseBlock
{
    public function name(): string
    {
        return 'list';
    }

    public function rules(): array
    {
        return [
            'ordered' => ['nullable', 'boolean'],
            'start' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $items = $renderer->sanitizer()->clean($block->content, ['ul', 'ol', 'li'], ['ul', 'ol']);

        if (trim($items) === '') {
            return '';
        }

        if (! str_starts_with(ltrim($items), '<li>')) {
            $items = '<li>'.$items.'</li>';
        }

        if (! $block->bool('ordered')) {
            return '<ul>'.$items.'</ul>';
        }

        $start = $block->int('start');

        return '<ol'.($start !== null && $start > 1 ? $this->attribute('start', $start) : '').'>'.$items.'</ol>';
    }
}
