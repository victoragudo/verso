<?php

namespace App\Verso\Editor\Contracts;

// Turns tokens inside sanitized text (like shortcodes) into trusted HTML.
interface InlineExpander
{
    /**
     * Splits sanitized inline HTML into text parts and block parts. Text parts stay inline HTML.
     *
     * @return list<array{html: string, block: bool}>
     */
    public function segments(string $html): array;

    /**
     * Names of the tokens used in a text, like ["form", "shop-product"].
     *
     * @return list<string>
     */
    public function names(string $html): array;
}
