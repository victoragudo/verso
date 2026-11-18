<?php

namespace App\Verso\Importer\Content;

// One Gutenberg block found in post content. A null name means plain HTML between blocks.
final class ParsedBlock
{
    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<ParsedBlock>  $innerBlocks
     * @param  list<string|null>  $innerContent  HTML pieces; null marks where an inner block goes.
     */
    public function __construct(
        public readonly ?string $name,
        public readonly array $attrs = [],
        public array $innerBlocks = [],
        public array $innerContent = [],
    ) {}

    // The full HTML of the block and its inner blocks, without block comments.
    public function html(): string
    {
        $html = '';
        $index = 0;

        foreach ($this->innerContent as $piece) {
            $html .= $piece ?? ($this->innerBlocks[$index++] ?? null)?->html() ?? '';
        }

        return $html;
    }

    // Only the HTML of this block, without inner blocks.
    public function ownHtml(): string
    {
        return implode('', array_filter($this->innerContent, is_string(...)));
    }

    public function attr(string $key): mixed
    {
        return $this->attrs[$key] ?? null;
    }
}
