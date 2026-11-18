<?php

namespace App\Verso\Layouts;

use App\Verso\Editor\BlockRegistry;
use Illuminate\Support\Str;

// The blocks and rules a new layout starts with, so people never start from an empty page.
final class LayoutStarters
{
    /** @return list<array<string, mixed>> */
    public static function blocks(LayoutKind $kind): array
    {
        return self::withIds(match ($kind) {
            LayoutKind::Template => [
                ['type' => 'section', 'attrs' => ['tag' => 'article'], 'children' => [
                    ['type' => 'breadcrumbs'],
                    ['type' => 'post-title', 'attrs' => ['level' => '1']],
                    ['type' => 'post-date'],
                    ['type' => 'post-featured-image'],
                    ['type' => 'post-content'],
                    ['type' => 'post-terms', 'attrs' => ['taxonomy' => 'tag']],
                ]],
            ],
            LayoutKind::Header => [
                ['type' => 'section', 'attrs' => ['tag' => 'div', 'design' => ['layout' => ['display' => 'flex', 'align' => 'center', 'justify' => 'between', 'gap' => 'var:spacing.medium'], 'spacing' => ['padding' => ['top' => 'var:spacing.medium', 'bottom' => 'var:spacing.medium', 'left' => 'var:spacing.medium', 'right' => 'var:spacing.medium']]]], 'children' => [
                    ['type' => 'site-logo'],
                    ['type' => 'navigation', 'attrs' => ['collapse' => true]],
                ]],
            ],
            LayoutKind::Footer => [
                ['type' => 'section', 'attrs' => ['tag' => 'div', 'design' => ['layout' => ['display' => 'flex', 'align' => 'center', 'justify' => 'between', 'wrap' => 'wrap', 'gap' => 'var:spacing.medium'], 'spacing' => ['padding' => ['top' => 'var:spacing.large', 'bottom' => 'var:spacing.large', 'left' => 'var:spacing.medium', 'right' => 'var:spacing.medium']]]], 'children' => [
                    ['type' => 'copyright'],
                    ['type' => 'social-links'],
                ]],
            ],
            LayoutKind::Popup => [
                ['type' => 'paragraph', 'content' => __('Write your message here. Keep it short and friendly.')],
                ['type' => 'button', 'attrs' => ['label' => __('Learn more'), 'url' => '/']],
            ],
        });
    }

    /** @return list<array{type: string, value: string, exclude: bool}> */
    public static function conditions(LayoutKind $kind): array
    {
        return $kind === LayoutKind::Template
            ? [['type' => 'page_kind', 'value' => 'entry', 'exclude' => false]]
            : [['type' => 'everywhere', 'value' => '', 'exclude' => false]];
    }

    /**
     * Gives every block a fresh id and drops block types the site does not know.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    protected static function withIds(array $blocks): array
    {
        $known = app(BlockRegistry::class);
        $result = [];

        foreach ($blocks as $block) {
            if ($known->find((string) $block['type']) === null) {
                continue;
            }

            $result[] = [
                'id' => 'b_'.Str::lower(Str::random(8)),
                'type' => $block['type'],
                'attrs' => $block['attrs'] ?? [],
                'content' => $block['content'] ?? '',
                'children' => self::withIds($block['children'] ?? []),
            ];
        }

        return $result;
    }
}
