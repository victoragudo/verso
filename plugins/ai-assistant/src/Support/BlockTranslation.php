<?php

namespace Verso\Plugins\AiAssistant\Support;

use Closure;

// Pulls the text pieces out of blocks and puts translated pieces back in the same places.
class BlockTranslation
{
    /**
     * @param  list<string>  $contentTypes  block types whose content is text
     * @param  list<string>  $attrs  block attributes that hold text
     */
    public function __construct(
        protected array $contentTypes,
        protected array $attrs,
    ) {}

    /**
     * Every text piece in reading order.
     *
     * @param  array<mixed>  $blocks
     * @return list<string>
     */
    public function segments(array $blocks): array
    {
        $segments = [];

        $this->walk($blocks, function (string $text) use (&$segments): string {
            $segments[] = $text;

            return $text;
        });

        return $segments;
    }

    /**
     * The same blocks with each piece replaced, in the order segments() gave them.
     *
     * @param  array<mixed>  $blocks
     * @param  list<string>  $translations
     * @return array<mixed>
     */
    public function apply(array $blocks, array $translations): array
    {
        $index = 0;

        return $this->walk($blocks, function (string $text) use (&$index, $translations): string {
            return $translations[$index++] ?? $text;
        });
    }

    /**
     * @param  array<mixed>  $blocks
     * @param  Closure(string): string  $change
     * @return array<mixed>
     */
    protected function walk(array $blocks, Closure $change): array
    {
        foreach ($blocks as $position => $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? '';

            if (in_array($type, $this->contentTypes, true) && $this->hasText($block['content'] ?? null)) {
                $block['content'] = $change((string) $block['content']);
            }

            if (is_array($block['attrs'] ?? null)) {
                $block['attrs'] = $this->walkAttrs($block['attrs'], $type, $change);
            }

            if (is_array($block['children'] ?? null)) {
                $block['children'] = $this->walk($block['children'], $change);
            }

            $blocks[$position] = $block;
        }

        return $blocks;
    }

    /**
     * @param  array<mixed>  $attrs
     * @param  Closure(string): string  $change
     * @return array<mixed>
     */
    protected function walkAttrs(array $attrs, mixed $type, Closure $change): array
    {
        foreach ($this->attrs as $name) {
            if ($this->hasText($attrs[$name] ?? null)) {
                $attrs[$name] = $change((string) $attrs[$name]);
            }
        }

        // Table cells are inline HTML in attrs.rows.
        if ($type === 'table' && is_array($attrs['rows'] ?? null)) {
            foreach ($attrs['rows'] as $r => $row) {
                if (! is_array($row)) {
                    continue;
                }

                foreach ($row as $c => $cell) {
                    if ($this->hasText($cell)) {
                        $row[$c] = $change((string) $cell);
                    }
                }

                $attrs['rows'][$r] = $row;
            }
        }

        return $attrs;
    }

    protected function hasText(mixed $value): bool
    {
        return is_string($value) && trim(strip_tags($value)) !== '';
    }
}
