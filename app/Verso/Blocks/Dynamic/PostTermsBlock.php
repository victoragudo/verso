<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// The categories, tags or other terms of the current entry, as links.
class PostTermsBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-terms';
    }

    public function label(): string
    {
        return __('Categories or tags');
    }

    public function description(): string
    {
        return __('The terms of the post, like its categories or tags.');
    }

    public function fields(): array
    {
        $taxonomies = [];

        foreach (app(TaxonomyRegistry::class)->all() as $name => $taxonomy) {
            $taxonomies[(string) $name] = __($taxonomy->pluralLabel);
        }

        return [
            new BlockField('taxonomy', __('Which terms'), BlockFieldType::Select, 'category', $taxonomies === [] ? ['category' => __('Categories')] : $taxonomies),
            new BlockField('prefix', __('Text before the terms'), BlockFieldType::Text, '', max: 40),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $terms = $entry->termsIn($this->text($block, 'taxonomy', 'category'));

        if ($terms->isEmpty()) {
            return '';
        }

        $prefix = $this->text($block, 'prefix');

        return '<p class="verso-post-terms">'.($prefix !== '' ? '<span>'.e($prefix).'</span> ' : '')
            .$terms->map(fn (Term $term): string => '<a href="'.e($term->url()).'" rel="tag">'.e($term->name).'</a>')->implode(', ').'</p>';
    }
}
