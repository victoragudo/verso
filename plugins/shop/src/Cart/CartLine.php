<?php

namespace Verso\Plugins\Shop\Cart;

use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Models\ProductVariant;

// One product (and maybe one variant) in the cart, with its current price.
final readonly class CartLine
{
    public function __construct(
        public string $key,
        public Product $product,
        public ?ProductVariant $variant,
        public int $quantity,
    ) {}

    public function name(): string
    {
        return $this->product->entry->title;
    }

    public function variantLabel(): ?string
    {
        return $this->variant?->label();
    }

    public function unitPrice(): int
    {
        return $this->product->priceFor($this->variant);
    }

    public function total(): int
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function url(): string
    {
        return $this->product->entry->url();
    }

    // The most that fits in the cart: the stock, or the shop limit.
    public function maxQuantity(): int
    {
        $limit = max(1, (int) config('shop.max_quantity'));
        $stock = $this->product->availableStock($this->variant);

        return $stock === null ? $limit : min($limit, $stock);
    }
}
