<?php

namespace Verso\Plugins\Shop\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\Shop\Support\Storefront;

// Shows one product with its price and an "Add to cart" button inside any entry.
class ProductBlock implements EmbeddableBlock, HasBlockFields, PluginBlock
{
    public const NAME = 'shop-product';

    public function __construct(protected Storefront $storefront) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('shop::Product');
    }

    public function description(): string
    {
        return __('shop::One product from the shop with its price and a buy button.');
    }

    public function shortcode(): bool
    {
        return true;
    }

    public function keyAttribute(): ?string
    {
        return 'product';
    }

    public function attributeAliases(): array
    {
        return ['slug' => 'product', 'id' => 'product'];
    }

    public function capability(): ?string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new BlockField(
                name: 'product',
                label: __('shop::Product'),
                help: __('shop::The slug of the product, like "linen-apron", or its id.'),
                required: true,
                max: 190,
            ),
            new BlockField(
                name: 'showButton',
                label: __('shop::Show the "Add to cart" button'),
                type: BlockFieldType::Toggle,
                default: true,
            ),
        ];
    }

    // Nothing is shown when the product is missing or not published.
    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $product = $this->storefront->find(is_scalar($attrs['product'] ?? null) ? (string) $attrs['product'] : '');

        if ($product === null) {
            return '';
        }

        return view('theme::shop.blocks.product', [
            'product' => $product,
            'storefront' => $this->storefront,
            'showButton' => filter_var($attrs['showButton'] ?? true, FILTER_VALIDATE_BOOL),
        ])->render();
    }
}
