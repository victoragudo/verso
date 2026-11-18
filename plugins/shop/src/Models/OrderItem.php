<?php

namespace Verso\Plugins\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order. Name and prices are copied, so later product changes do not touch old orders.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $name
 * @property string|null $variant_label
 * @property string|null $sku
 * @property int $unit_price
 * @property int $quantity
 * @property int $line_total
 * @property-read Order $order
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 */
class OrderItem extends Model
{
    protected $table = 'shop_order_items';

    protected $fillable = ['product_id', 'variant_id', 'name', 'variant_label', 'sku', 'unit_price', 'quantity', 'line_total'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
