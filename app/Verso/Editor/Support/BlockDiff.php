<?php

namespace App\Verso\Editor\Support;

// Compares two block lists by block id: which blocks were added, removed, changed or moved.
final class BlockDiff
{
    public const string ADDED = 'added';

    public const string REMOVED = 'removed';

    public const string CHANGED = 'changed';

    public const string MOVED = 'moved';

    /**
     * Every difference, in the order of the newer list, then the removed blocks.
     *
     * @param  array<int, mixed>  $before
     * @param  array<int, mixed>  $after
     * @return list<array{id: string, type: string, change: string, text: string}>
     */
    public function compare(array $before, array $after): array
    {
        $old = $this->flatten($before);
        $new = $this->flatten($after);
        $changes = [];

        foreach ($new as $id => $block) {
            $previous = $old[$id] ?? null;

            if ($previous === null) {
                $changes[] = $this->change($block, self::ADDED);
            } elseif ($previous['fingerprint'] !== $block['fingerprint']) {
                $changes[] = $this->change($block, self::CHANGED);
            } elseif ($previous['place'] !== $block['place']) {
                $changes[] = $this->change($block, self::MOVED);
            }
        }

        foreach ($old as $id => $block) {
            if (! isset($new[$id])) {
                $changes[] = $this->change($block, self::REMOVED);
            }
        }

        return $changes;
    }

    /**
     * @param  array{id: string, type: string, text: string}  $block
     * @return array{id: string, type: string, change: string, text: string}
     */
    protected function change(array $block, string $change): array
    {
        return ['id' => $block['id'], 'type' => $block['type'], 'change' => $change, 'text' => $block['text']];
    }

    /**
     * Every block by id with its place (parent id and position), a fingerprint of its own data and a short text.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<string, array{id: string, type: string, place: string, fingerprint: string, text: string}>
     */
    protected function flatten(array $blocks, string $parent = ''): array
    {
        $flat = [];

        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block) || ! is_string($block['id'] ?? null)) {
                continue;
            }

            $content = is_string($block['content'] ?? null) ? $block['content'] : '';
            $flat[$block['id']] = [
                'id' => $block['id'],
                'type' => (string) ($block['type'] ?? ''),
                'place' => $parent.'#'.$index,
                'fingerprint' => hash('xxh128', (string) json_encode([$block['type'] ?? '', $block['attrs'] ?? [], $content])),
                'text' => mb_substr(trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5)), 0, 120),
            ];

            if (is_array($block['children'] ?? null)) {
                $flat = [...$flat, ...$this->flatten($block['children'], $block['id'])];
            }
        }

        return $flat;
    }
}
