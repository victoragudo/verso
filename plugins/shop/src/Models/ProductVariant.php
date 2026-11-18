<?php

namespace Verso\Plugins\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One option of a product, like "Size: M", with its own stock and an optional price.
 *
 * @property int $id
 * @property int $product_id
 * @property string $option_name
 * @property string $option_value
 * @property string|null $sku
 * @property int|null $price
 * @property int $stock
 * @property int $position
 * @property-read Product $product
 */
class ProductVariant extends Model
{
    protected $table = 'shop_product_variants';

    protected $fillable = ['product_id', 'option_name', 'option_value', 'sku', 'price', 'stock', 'position'];

    protected $attributes = ['stock' => 0, 'position' => 0];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Text shown to people, like "Size: M".
    public function label(): string
    {
        return $this->option_name.': '.$this->option_value;
    }
}
