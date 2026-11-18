<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// Preformatted source code. The content is plain text and is always escaped.
class CodeBlock extends BaseBlock
{
    // Language names look like "php", "c++" or "objective-c".
    public const string LANGUAGE_PATTERN = '/^[A-Za-z0-9_+#.-]+$/';

    public function name(): string
    {
        return 'code';
    }

    public function rules(): array
    {
        return [
            'language' => ['nullable', 'string', 'max:40', 'regex:'.self::LANGUAGE_PATTERN],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        if ($block->content === '') {
            return '';
        }

        $language = strtolower($block->string('language'));
        $class = preg_match(self::LANGUAGE_PATTERN, $language) === 1 ? $this->attribute('class', 'language-'.$language) : '';

        return '<pre><code'.$class.'>'.htmlspecialchars($block->content, ENT_NOQUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8').'</code></pre>';
    }
}
