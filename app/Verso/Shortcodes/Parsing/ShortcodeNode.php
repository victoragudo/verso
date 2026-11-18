<?php

namespace App\Verso\Shortcodes\Parsing;

// One shortcode read from a text, like [form slug="contact"].
final readonly class ShortcodeNode
{
    /**
     * @param  array<string, string|true>  $attrs  attribute values, true for flags without a value
     * @param  list<TextNode|ShortcodeNode>  $children  parsed inner text of an enclosing shortcode
     */
    public function __construct(
        public string $name,
        public array $attrs,
        public string $raw,
        public ?string $inner = null,
        public array $children = [],
        public ?string $error = null,
    ) {}

    public function enclosing(): bool
    {
        return $this->inner !== null;
    }

    public function valid(): bool
    {
        return $this->error === null;
    }
}
