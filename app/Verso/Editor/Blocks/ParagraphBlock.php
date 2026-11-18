<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A paragraph of text with inline marks.
class ParagraphBlock extends BaseBlock
{
    public function name(): string
    {
        return 'paragraph';
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        // Sanitize first, then expand shortcodes with the trusted HTML of their blocks.
        // A block cannot sit inside <p>, so the text around it gets its own paragraphs.
        $html = '';
        $text = '';

        foreach ($renderer->inlineSegments($renderer->sanitizeInline($block->content)) as $segment) {
            if (! $segment['block']) {
                $text .= $segment['html'];

                continue;
            }

            $html .= $this->paragraph($text).$segment['html'];
            $text = '';
        }

        $html .= $this->paragraph($text);

        return $html;
    }

    // A <p> with the text, or nothing when it holds only spaces and line breaks.
    protected function paragraph(string $html): string
    {
        return trim(str_replace('<br>', '', $html)) === '' ? '' : '<p>'.$html.'</p>';
    }
}
