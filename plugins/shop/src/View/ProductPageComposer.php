<?php

namespace Verso\Plugins\Shop\View;

use App\Verso\Content\Models\Entry;
use Illuminate\View\View;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Support\Storefront;

// Gives the product page its shop data: price, stock, variants and gallery.
class ProductPageComposer
{
    public function __construct(protected Storefront $storefront) {}

    public function compose(View $view): void
    {
        $entry = $view->getData()['entry'] ?? null;
        $product = $entry instanceof Entry
            ? Product::query()->with('variants')->where('entry_id', $entry->id)->first()
            : null;

        $product?->setRelation('entry', $entry);

        $view->with([
            'product' => $product,
            'storefront' => $this->storefront,
            'gallery' => $product === null ? [] : $this->storefront->gallery($product, (string) config('verso.media.images.featured_sizes')),
        ]);
    }
}
