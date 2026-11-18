<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\I18n\Translate;
use Illuminate\Validation\Rule;

// A box that holds other blocks, sections too. With attrs.style it becomes a flex or grid layout.
// It is the main layout block of headers, footers and landing pages.
class SectionBlock extends BaseBlock
{
    // HTML elements a section can be.
    public const TAGS = ['div', 'section', 'header', 'footer', 'aside', 'nav', 'main', 'article'];

    // Plain names of the elements for the editor.
    public const TAG_LABELS = [
        'div' => ['label' => 'Plain box'],
        'section' => ['label' => 'Section'],
        'header' => ['label' => 'Header area'],
        'footer' => ['label' => 'Footer area'],
        'aside' => ['label' => 'Side note'],
        'nav' => ['label' => 'Navigation'],
        'main' => ['label' => 'Main content'],
        'article' => ['label' => 'Article'],
    ];

    public function name(): string
    {
        return 'section';
    }

    public function rules(): array
    {
        return [
            'tag' => ['sometimes', 'string', Rule::in(self::TAGS)],
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    // Any block fits inside, except a single column (that one belongs in "columns").
    public function acceptsChild(string $type): bool
    {
        return $type !== 'column';
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $tag = in_array($block->attrs['tag'] ?? null, self::TAGS, true) ? $block->attrs['tag'] : 'div';
        $label = is_string($block->attrs['label'] ?? null) && $block->attrs['label'] !== '' ? $block->attrs['label'] : null;

        return '<'.$tag.' class="verso-section"'.$this->attribute('aria-label', $label).'>'.$renderer->renderChildren($block).'</'.$tag.'>';
    }

    /**
     * How the editor shows the section: a box with a "Kind of area" field that holds other blocks.
     *
     * @return array<string, mixed>
     */
    public static function editorDefinition(): array
    {
        return [
            'name' => 'section',
            'label' => Translate::text('Section'),
            'description' => Translate::text('A box that groups blocks side by side or stacked.'),
            'fields' => [[
                'name' => 'tag', 'label' => Translate::text('Kind of area'), 'type' => 'select', 'default' => 'div',
                'options' => array_map(fn (string $tag): array => ['value' => $tag, 'label' => Translate::text(self::TAG_LABELS[$tag]['label'])], self::TAGS),
                'help' => Translate::text('Helps screen readers. ":box" fits most cases.', ['box' => Translate::text(self::TAG_LABELS['div']['label'])]), 'required' => false, 'min' => null, 'max' => null,
            ]],
            'children' => true,
            'shortcode' => false,
            'aliases' => [],
        ];
    }
}
