<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A quote made of one or more paragraphs, with an optional citation.
class QuoteBlock extends BaseBlock
{
    public function name(): string
    {
        return 'quote';
    }

    public function rules(): array
    {
        return [
            'citation' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $text = trim($renderer->sanitizer()->clean($block->content, ['p']));

        if (trim($renderer->sanitizer()->text($text)) === '') {
            return '';
        }

        if (! str_starts_with($text, '<p>')) {
            $text = '<p>'.$text.'</p>';
        }

        $citation = trim($renderer->sanitizeInline($block->string('citation')));

        return '<blockquote>'.$text.($citation === '' ? '' : '<cite>'.$citation.'</cite>').'</blockquote>';
    }
}
