<?php

namespace Verso\Plugins\Shop\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\Shop\Support\Storefront;

// Shows a grid of products, like the newest ones or the cheapest ones.
class ProductGridBlock implements HasBlockFields, PluginBlock
{
    public const NAME = 'shop-product-grid';

    // Columns the grid can have on wide screens.
    // Category value that means "no filter". Slugs never start with "_", so it cannot clash.
    public const ALL_CATEGORIES = '_all';

    public const COLUMNS = ['2', '3', '4'];

    public function __construct(protected Storefront $storefront) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('shop::Product grid');
    }

    public function description(): string
    {
        return __('shop::A grid of products from the shop.');
    }

    public function fields(): array
    {
        return [
            new BlockField(
                name: 'limit',
                label: __('shop::Number of products'),
                type: BlockFieldType::Number,
                default: 6,
                min: 1,
                max: max(1, (int) config('shop.grid_max')),
            ),
            new BlockField(
                name: 'columns',
                label: __('shop::Columns'),
                type: BlockFieldType::Select,
                default: '3',
                options: $this->columnOptions(),
            ),
            new BlockField(
                name: 'sort',
                label: __('shop::Order'),
                type: BlockFieldType::Select,
                default: 'newest',
                options: $this->storefront->sortLabels(),
            ),
            new BlockField(
                name: 'category',
                label: __('shop::Category'),
                type: BlockFieldType::Select,
                default: self::ALL_CATEGORIES,
                options: $this->categoryOptions(),
                help: __('shop::Show only products of one category (and its sub categories).'),
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columnOptions(): array
    {
        $options = [];

        foreach (self::COLUMNS as $count) {
            $options[(int) $count] = Translate::icu('shop::{count, plural, one {# column} other {# columns}}', ['count' => (int) $count]);
        }

        return $options;
    }

    /**
     * "All categories" plus every product category, by slug. Select values cannot be empty in the editor.
     *
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        $options = [self::ALL_CATEGORIES => __('shop::All categories')];

        foreach ($this->storefront->categories() as $category) {
            $options[$category->slug] = $category->name;
        }

        return $options;
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $limit = is_numeric($attrs['limit'] ?? null) ? (int) $attrs['limit'] : 6;
        $limit = min(max(1, $limit), max(1, (int) config('shop.grid_max')));
        $columns = is_scalar($attrs['columns'] ?? null) && in_array((string) $attrs['columns'], self::COLUMNS, true) ? (string) $attrs['columns'] : '3';
        $sort = is_string($attrs['sort'] ?? null) && in_array($attrs['sort'], Storefront::SORTS, true) ? $attrs['sort'] : 'newest';

        $slug = is_string($attrs['category'] ?? null) ? trim($attrs['category']) : '';
        $slug = $slug === self::ALL_CATEGORIES ? '' : $slug;
        $category = $slug === '' ? null : $this->storefront->category($slug);

        if ($slug !== '' && $category === null) {
            return '';
        }

        $products = $this->storefront->take($sort, $limit, $category);

        if ($products->isEmpty()) {
            return '';
        }

        return view('theme::shop.blocks.grid', [
            'products' => $products,
            'storefront' => $this->storefront,
            'columns' => $columns,
        ])->render();
    }
}
