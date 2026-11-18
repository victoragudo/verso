<?php

namespace App\Verso\Shortcodes\Parsing;

// Plain text between shortcodes. It is printed as it is.
final readonly class TextNode
{
    public function __construct(public string $text) {}
}
