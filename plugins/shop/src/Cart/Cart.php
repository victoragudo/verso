<?php

namespace Verso\Plugins\Shop\Cart;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Verso\Plugins\Shop\Exceptions\OutOfStock;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Models\ProductVariant;
use Verso\Plugins\Shop\Support\ShopSettings;
use Verso\Plugins\Shop\Support\Totals;

// The visitor's cart, kept in the session. Prices are always read from the products.
class Cart
{
    /** @var Collection<string, CartLine>|null */
    protected ?Collection $lines = null;

    public function __construct(
        protected Session $session,
        protected ShopSettings $settings,
        protected string $sessionKey,
    ) {}

    // The key of a line: one line per product and variant.
    public static function lineKey(int $productId, ?int $variantId = null): string
    {
        return $variantId === null ? "p{$productId}" : "p{$productId}-v{$variantId}";
    }

    // Add items. The quantity in the cart never goes over the stock or the shop limit.
    public function add(Product $product, ?ProductVariant $variant, int $quantity): CartLine
    {
        $key = self::lineKey($product->id, $variant?->id);
        $wanted = ($this->stored()[$key]['quantity'] ?? 0) + max(1, $quantity);
        $line = new CartLine($key, $product, $variant, $wanted);

        $this->guardStock($line);

        $this->write($key, $product->id, $variant?->id, min($wanted, $line->maxQuantity()));

        return $this->lines()->get($key) ?? $line;
    }

    // Set the quantity of a line. Zero removes it.
    public function update(string $key, int $quantity): void
    {
        $stored = $this->stored()[$key] ?? null;

        if ($stored === null) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($key);

            return;
        }

        $line = $this->lines()->get($key);

        if ($line === null) {
            return;
        }

        $this->guardStock(new CartLine($key, $line->product, $line->variant, $quantity));
        $this->write($key, $stored['product'], $stored['variant'], min($quantity, $line->maxQuantity()));
    }

    public function remove(string $key): void
    {
        $stored = $this->stored();
        unset($stored[$key]);

        $this->session->put($this->sessionKey, $stored);
        $this->lines = null;
    }

    public function clear(): void
    {
        $this->session->forget($this->sessionKey);
        $this->lines = null;
    }

    /**
     * Lines with products that can still be bought. Others are dropped from the session.
     *
     * @return Collection<string, CartLine>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $stored = $this->stored();
        $products = Product::query()
            ->sellable()
            ->with(['entry', 'variants'])
            ->whereIn('id', array_column($stored, 'product'))
            ->get()
            ->keyBy('id');

        $lines = new Collection;

        foreach ($stored as $key => $item) {
            $product = $products->get($item['product']);
            $variant = $product?->findVariant($item['variant']);

            if ($product === null || ($item['variant'] !== null && $variant === null) || ($variant === null && $product->hasVariants())) {
                continue;
            }

            $lines->put($key, new CartLine($key, $product, $variant, $item['quantity']));
        }

        if ($lines->count() !== count($stored)) {
            $this->session->put($this->sessionKey, array_intersect_key($stored, $lines->all()));
        }

        return $this->lines = $lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    // Number of items, counting quantities.
    public function count(): int
    {
        return (int) $this->lines()->sum(fn (CartLine $line): int => $line->quantity);
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum(fn (CartLine $line): int => $line->total());
    }

    public function totals(): Totals
    {
        return $this->settings->totals($this->subtotal());
    }

    // Stop when the wanted quantity is more than the stock.
    protected function guardStock(CartLine $line): void
    {
        $stock = $line->product->availableStock($line->variant);

        if ($stock !== null && $line->quantity > $stock) {
            throw OutOfStock::for($line->variant ? $line->name().' ('.$line->variant->label().')' : $line->name(), $stock);
        }
    }

    protected function write(string $key, int $productId, ?int $variantId, int $quantity): void
    {
        $stored = $this->stored();
        $stored[$key] = ['product' => $productId, 'variant' => $variantId, 'quantity' => $quantity];

        $this->session->put($this->sessionKey, $stored);
        $this->lines = null;
    }

    /**
     * The raw cart from the session, with broken items left out.
     *
     * @return array<string, array{product: int, variant: int|null, quantity: int}>
     */
    protected function stored(): array
    {
        $raw = $this->session->get($this->sessionKey, []);
        $items = [];

        foreach (is_array($raw) ? $raw : [] as $key => $item) {
            if (! is_string($key) || ! is_array($item) || ! is_numeric($item['product'] ?? null) || ! is_numeric($item['quantity'] ?? null)) {
                continue;
            }

            $items[$key] = [
                'product' => (int) $item['product'],
                'variant' => is_numeric($item['variant'] ?? null) ? (int) $item['variant'] : null,
                'quantity' => max(1, (int) $item['quantity']),
            ];
        }

        return $items;
    }
}
