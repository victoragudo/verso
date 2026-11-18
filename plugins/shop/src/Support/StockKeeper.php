<?php

namespace Verso\Plugins\Shop\Support;

use Verso\Plugins\Shop\Exceptions\OutOfStock;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Models\ProductVariant;

// Takes items out of stock and puts them back. Products without stock tracking are left alone.
class StockKeeper
{
    // Take items out of stock in one safe query, so two orders cannot sell the last item twice.
    public function reduce(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (! $product->track_stock || $quantity <= 0) {
            return;
        }

        $query = $variant !== null
            ? ProductVariant::query()->whereKey($variant->id)
            : Product::query()->whereKey($product->id);

        $updated = $query->where('stock', '>=', $quantity)->decrement('stock', $quantity);

        if ($updated === 0) {
            $fresh = $variant?->fresh() ?? $product->fresh();

            throw OutOfStock::for($this->name($product, $variant), max(0, (int) $fresh?->stock));
        }

        $model = $variant ?? $product;
        $model->stock -= $quantity;
        $model->syncOriginalAttribute('stock');
    }

    public function restore(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (! $product->track_stock || $quantity <= 0) {
            return;
        }

        $model = $variant ?? $product;
        $model->newQuery()->whereKey($model->getKey())->increment('stock', $quantity);
        $model->stock += $quantity;
        $model->syncOriginalAttribute('stock');
    }

    protected function name(Product $product, ?ProductVariant $variant): string
    {
        $title = (string) $product->entry?->title;

        return $variant === null ? $title : $title.' ('.$variant->label().')';
    }
}
