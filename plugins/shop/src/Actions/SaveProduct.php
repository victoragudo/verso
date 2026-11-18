<?php

namespace Verso\Plugins\Shop\Actions;

use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Verso\Plugins\Shop\Models\Product;

/**
 * Saves the shop data of a product entry and its variants.
 *
 * @phpstan-type ProductData array{price: int, sku: string|null, track_stock: bool, stock: int, gallery: list<int>, variants: list<array{id: int|null, option_name: string, option_value: string, sku: string|null, price: int|null, stock: int}>}
 */
class SaveProduct
{
    public function __construct(protected PageCache $pageCache) {}

    /**
     * @param  ProductData  $data
     */
    public function handle(Entry $entry, array $data): Product
    {
        if ($entry->type !== Product::TYPE) {
            throw new InvalidArgumentException('Only product entries can have shop data.');
        }

        $product = DB::transaction(function () use ($entry, $data): Product {
            $product = Product::forEntry($entry);
            $product->fill([
                'price' => max(0, $data['price']),
                'sku' => $data['sku'],
                'track_stock' => $data['track_stock'],
                'stock' => max(0, $data['stock']),
                'gallery' => $data['gallery'],
            ]);
            $product->save();

            $this->syncVariants($product, $data['variants']);

            return $product;
        });

        $this->pageCache->flush();

        return $product->load('variants');
    }

    /**
     * Update listed variants, add new ones and delete the ones that are gone.
     *
     * @param  list<array{id: int|null, option_name: string, option_value: string, sku: string|null, price: int|null, stock: int}>  $variants
     */
    protected function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];

        foreach ($variants as $position => $data) {
            $variant = $data['id'] !== null ? $existing->get($data['id']) : null;
            $variant ??= $product->variants()->make();

            $variant->fill([
                'option_name' => $data['option_name'],
                'option_value' => $data['option_value'],
                'sku' => $data['sku'],
                'price' => $data['price'] === null ? null : max(0, $data['price']),
                'stock' => max(0, $data['stock']),
                'position' => $position,
            ]);
            $variant->save();

            $kept[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $kept)->delete();
        $product->unsetRelation('variants');
    }
}
