<?php

namespace App\Verso\Shortcodes\Parsing;

use App\Verso\I18n\Translate;
use Closure;

// Reads shortcodes from a text with a small tokenizer, one character at a time. No eval and no regex on the whole text.
// Supported: [name a="v" b='v' c=v flag], [name /], [name]inner[/name] and the escape [[name]] that prints [name].
class ShortcodeParser
{
    protected int $count = 0;

    public function __construct(protected ShortcodeLimits $limits) {}

    /**
     * Splits a text into text nodes and shortcode nodes.
     *
     * @param  (Closure(string): bool)|null  $enclosing  true when a shortcode name may wrap inner text
     * @return list<TextNode|ShortcodeNode>
     */
    public function parse(string $text, ?Closure $enclosing = null): array
    {
        $this->count = 0;

        return $this->parseLevel($text, $enclosing ?? fn (string $name): bool => true, 1);
    }

    // True when the text holds at least one shortcode, valid or not.
    public function contains(string $text): bool
    {
        if (! str_contains($text, '[')) {
            return false;
        }

        foreach ($this->parse($text) as $node) {
            if ($node instanceof ShortcodeNode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every shortcode in the text, inner ones too.
     *
     * @return list<ShortcodeNode>
     */
    public function all(string $text): array
    {
        return $this->flatten($this->parse($text));
    }

    /**
     * @param  list<TextNode|ShortcodeNode>  $nodes
     * @return list<ShortcodeNode>
     */
    protected function flatten(array $nodes): array
    {
        $found = [];

        foreach ($nodes as $node) {
            if ($node instanceof ShortcodeNode) {
                $found = [...$found, $node, ...$this->flatten($node->children)];
            }
        }

        return $found;
    }

    /**
     * @param  Closure(string): bool  $enclosing
     * @return list<TextNode|ShortcodeNode>
     */
    protected function parseLevel(string $text, Closure $enclosing, int $depth): array
    {
        $nodes = [];
        $buffer = '';
        $position = 0;
        $length = strlen($text);

        while ($position < $length) {
            $open = strpos($text, '[', $position);

            if ($open === false) {
                $buffer .= substr($text, $position);
                break;
            }

            $buffer .= substr($text, $position, $open - $position);

            $escaped = $this->readEscape($text, $open);

            if ($escaped !== null) {
                $buffer .= $escaped['text'];
                $position = $escaped['end'];

                continue;
            }

            $tag = $this->readTag($text, $open);

            if ($tag === null) {
                $buffer .= '[';
                $position = $open + 1;

                continue;
            }

            if ($this->count >= $this->limits->maxPerText) {
                $buffer .= substr($text, $open, $tag['end'] - $open);
                $position = $tag['end'];

                continue;
            }

            $this->count++;
            $node = $this->node($text, $tag, $open, $enclosing, $depth);
            $position = $node['end'];

            if ($buffer !== '') {
                $nodes[] = new TextNode($buffer);
                $buffer = '';
            }

            $nodes[] = $node['node'];
        }

        if ($buffer !== '') {
            $nodes[] = new TextNode($buffer);
        }

        return $nodes;
    }

    /**
     * Builds the node for an opening tag, with the inner text when a closing tag follows.
     *
     * @param  array{name: string, attrs: array<string, string|true>, end: int, selfClosing: bool, error: ?string}  $tag
     * @param  Closure(string): bool  $enclosing
     * @return array{node: ShortcodeNode, end: int}
     */
    protected function node(string $text, array $tag, int $open, Closure $enclosing, int $depth): array
    {
        $closing = '[/'.$tag['name'].']';
        $close = ! $tag['selfClosing'] && $enclosing($tag['name']) ? strpos($text, $closing, $tag['end']) : false;

        if ($close === false) {
            return [
                'node' => new ShortcodeNode($tag['name'], $tag['attrs'], substr($text, $open, $tag['end'] - $open), error: $tag['error']),
                'end' => $tag['end'],
            ];
        }

        $inner = substr($text, $tag['end'], $close - $tag['end']);
        $end = $close + strlen($closing);
        $raw = substr($text, $open, $end - $open);
        $tooDeep = $depth >= $this->limits->maxDepth && str_contains($inner, '[');
        $children = $tooDeep ? [new TextNode($inner)] : $this->parseLevel($inner, $enclosing, $depth + 1);
        $error = $tag['error'] ?? ($tooDeep ? Translate::text('Shortcodes are nested too deeply.') : null);

        return ['node' => new ShortcodeNode($tag['name'], $tag['attrs'], $raw, $inner, $children, $error), 'end' => $end];
    }

    /**
     * [[name ...]] prints [name ...] without running it.
     *
     * @return array{text: string, end: int}|null
     */
    protected function readEscape(string $text, int $open): ?array
    {
        if (($text[$open + 1] ?? '') !== '[') {
            return null;
        }

        $tag = $this->readTag($text, $open + 1);

        if ($tag === null) {
            return null;
        }

        $close = $tag['selfClosing'] ? false : strpos($text, '[/'.$tag['name'].']]', $tag['end']);

        if ($close !== false) {
            $end = $close + strlen('[/'.$tag['name'].']]');

            return ['text' => substr($text, $open + 1, $end - $open - 2), 'end' => $end];
        }

        if (($text[$tag['end']] ?? '') !== ']') {
            return null;
        }

        return ['text' => substr($text, $open + 1, $tag['end'] - $open - 1), 'end' => $tag['end'] + 1];
    }

    /**
     * Reads one opening tag that starts at $open. Returns null when the text is not a shortcode.
     *
     * @return array{name: string, attrs: array<string, string|true>, end: int, selfClosing: bool, error: ?string}|null
     */
    protected function readTag(string $text, int $open): ?array
    {
        $position = $open + 1;
        $name = $this->readName($text, $position, true);

        if ($name === null) {
            return null;
        }

        $attrs = [];
        $error = null;
        $length = strlen($text);

        while ($position < $length) {
            $position = $this->skipSpaces($text, $position);
            $char = $text[$position] ?? '';

            if ($char === ']') {
                return ['name' => $name, 'attrs' => $attrs, 'end' => $position + 1, 'selfClosing' => false, 'error' => $error];
            }

            if ($char === '/' && ($text[$position + 1] ?? '') === ']') {
                return ['name' => $name, 'attrs' => $attrs, 'end' => $position + 2, 'selfClosing' => true, 'error' => $error];
            }

            $attribute = $this->readName($text, $position);

            if ($attribute === null) {
                return null;
            }

            if (($text[$position] ?? '') !== '=') {
                $attrs[$attribute] = true;

                continue;
            }

            $value = $this->readValue($text, $position + 1);

            if ($value === null) {
                return null;
            }

            $position = $value['end'];
            $decoded = html_entity_decode($value['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (mb_strlen($decoded) > $this->limits->maxAttributeLength) {
                $error ??= Translate::text('The value of ":attribute" is too long.', ['attribute' => $attribute]);
            }

            $attrs[$attribute] = $decoded;
        }

        return null;
    }

    // A name starts with a letter and holds letters, numbers, "_" and "-".
    // Shortcode names are lowercase, so text like [Lisbon bakery] is not read as a shortcode.
    protected function readName(string $text, int &$position, bool $lowercase = false): ?string
    {
        $start = $position;
        $length = strlen($text);
        $letter = $lowercase ? ctype_lower(...) : ctype_alpha(...);

        if ($position >= $length || ! $letter($text[$position])) {
            return null;
        }

        while ($position < $length && $this->nameChar($text[$position], $lowercase)) {
            $position++;
        }

        return substr($text, $start, $position - $start);
    }

    protected function nameChar(string $char, bool $lowercase): bool
    {
        if ($char === '_' || $char === '-' || ctype_digit($char)) {
            return true;
        }

        return $lowercase ? ctype_lower($char) : ctype_alpha($char);
    }

    /**
     * A quoted value can hold spaces and brackets. A value without quotes ends at a space or "]".
     *
     * @return array{value: string, end: int}|null
     */
    protected function readValue(string $text, int $position): ?array
    {
        $quote = $text[$position] ?? '';

        if ($quote === '"' || $quote === "'") {
            $close = strpos($text, $quote, $position + 1);

            return $close === false ? null : ['value' => substr($text, $position + 1, $close - $position - 1), 'end' => $close + 1];
        }

        $start = $position;
        $length = strlen($text);

        while ($position < $length && ! ctype_space($text[$position]) && $text[$position] !== ']' && $text[$position] !== '[') {
            $position++;
        }

        return $position === $start ? null : ['value' => substr($text, $start, $position - $start), 'end' => $position];
    }

    protected function skipSpaces(string $text, int $position): int
    {
        $length = strlen($text);

        while ($position < $length && ctype_space($text[$position])) {
            $position++;
        }

        return $position;
    }
}
