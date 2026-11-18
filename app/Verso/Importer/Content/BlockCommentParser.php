<?php

namespace App\Verso\Importer\Content;

// Splits Gutenberg post content ("<!-- wp:paragraph -->...") into a tree of blocks.
class BlockCommentParser
{
    // Same token pattern as the WordPress block parser.
    protected const TOKEN = '/<!--\s+(?<closer>\/)?wp:(?<namespace>[a-z][a-z0-9_-]*\/)?(?<name>[a-z][a-z0-9_-]*)\s+(?<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?<void>\/)?-->/s';

    // True when the content has at least one block comment.
    public function hasBlocks(string $content): bool
    {
        return str_contains($content, '<!-- wp:');
    }

    /**
     * @return list<ParsedBlock>
     */
    public function parse(string $content): array
    {
        $root = new ParsedBlock(null);
        /** @var list<ParsedBlock> $stack */
        $stack = [];
        $offset = 0;

        preg_match_all(self::TOKEN, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            [$token, $start] = $match[0];
            $this->addHtml($stack, $root, substr($content, $offset, $start - $offset));
            $offset = $start + strlen($token);

            $name = ($match['namespace'][0] !== '' ? $match['namespace'][0] : 'core/').$match['name'][0];

            if ($match['closer'][0] === '/') {
                $this->close($stack, $root, $name);

                continue;
            }

            $block = new ParsedBlock($name, $this->attrs($match['attrs'][0] ?? ''));

            if (($match['void'][0] ?? '') === '/') {
                $this->append($stack, $root, $block);
            } else {
                $stack[] = $block;
            }
        }

        $this->addHtml($stack, $root, substr($content, $offset));

        while ($stack !== []) {
            $this->close($stack, $root, null);
        }

        return $this->topLevel($root);
    }

    /** @param list<ParsedBlock> $stack */
    protected function addHtml(array $stack, ParsedBlock $root, string $html): void
    {
        if ($html === '') {
            return;
        }

        $current = $stack === [] ? null : $stack[array_key_last($stack)];

        if ($current !== null) {
            $current->innerContent[] = $html;

            return;
        }

        $root->innerBlocks[] = new ParsedBlock(null, [], [], [$html]);
        $root->innerContent[] = null;
    }

    // Close the newest open block. A closer without its opener is ignored.
    /** @param list<ParsedBlock> $stack */
    protected function close(array &$stack, ParsedBlock $root, ?string $name): void
    {
        if ($stack === []) {
            return;
        }

        if ($name !== null && ! in_array($name, array_map(fn (ParsedBlock $block) => $block->name, $stack), true)) {
            return;
        }

        do {
            $block = array_pop($stack);
            $this->append($stack, $root, $block);
        } while ($name !== null && $block->name !== $name && $stack !== []);
    }

    /** @param list<ParsedBlock> $stack */
    protected function append(array $stack, ParsedBlock $root, ParsedBlock $block): void
    {
        $parent = $stack === [] ? $root : $stack[array_key_last($stack)];
        $parent->innerBlocks[] = $block;
        $parent->innerContent[] = null;
    }

    /** @return array<string, mixed> */
    protected function attrs(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $attrs = json_decode($json, true);

        return is_array($attrs) ? $attrs : [];
    }

    /**
     * Top level blocks. Plain HTML pieces that sit next to each other are joined.
     *
     * @return list<ParsedBlock>
     */
    protected function topLevel(ParsedBlock $root): array
    {
        $blocks = [];

        foreach ($root->innerBlocks as $block) {
            $last = $blocks === [] ? null : $blocks[array_key_last($blocks)];

            if ($block->name === null && $last !== null && $last->name === null) {
                $last->innerContent[0] .= $block->ownHtml();

                continue;
            }

            $blocks[] = $block;
        }

        return $blocks;
    }
}
