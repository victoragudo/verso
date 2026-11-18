<?php

namespace Verso\Plugins\Shop\Models;

use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop data of a product entry: price, SKU, stock and gallery.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $price
 * @property string|null $sku
 * @property bool $track_stock
 * @property int $stock
 * @property list<int>|null $gallery
 * @property-read Entry|null $entry
 * @property-read Collection<int, ProductVariant> $variants
 */
class Product extends Model
{
    // Content type name of product entries.
    public const TYPE = 'product';

    // Taxonomy that groups products, like "Mugs" or "Kitchen".
    public const CATEGORY_TAXONOMY = 'product_category';

    protected $table = 'shop_products';

    protected $fillable = ['entry_id', 'price', 'sku', 'track_stock', 'stock', 'gallery'];

    protected $attributes = ['price' => 0, 'track_stock' => false, 'stock' => 0];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'track_stock' => 'boolean',
            'stock' => 'integer',
            'gallery' => 'array',
        ];
    }

    /** @return BelongsTo<Entry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    // The shop data of an entry, or a new unsaved record with default values.
    public static function forEntry(Entry $entry): self
    {
        return self::query()->firstOrNew(['entry_id' => $entry->id]);
    }

    // Products whose entry is published, so visitors can buy them.
    /** @param Builder<self> $query */
    public function scopeSellable(Builder $query): void
    {
        $query->whereHas('entry', fn (Builder $entries) => $entries
            ->where('type', self::TYPE)
            ->where('status', EntryStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()));
    }

    public function isSellable(): bool
    {
        return $this->entry !== null && $this->entry->type === self::TYPE && $this->entry->isPublished();
    }

    public function hasVariants(): bool
    {
        return $this->variants->isNotEmpty();
    }

    public function findVariant(?int $id): ?ProductVariant
    {
        return $id === null ? null : $this->variants->firstWhere('id', $id);
    }

    // The price of the product, or of the variant when it has its own price.
    public function priceFor(?ProductVariant $variant = null): int
    {
        return $variant->price ?? $this->price;
    }

    // Lowest and highest price over all variants, for "from" prices.
    /** @return array{0: int, 1: int} */
    public function priceRange(): array
    {
        $prices = $this->variants->map(fn (ProductVariant $variant): int => $this->priceFor($variant));

        if ($prices->isEmpty()) {
            return [$this->price, $this->price];
        }

        return [(int) $prices->min(), (int) $prices->max()];
    }

    // How many can be bought. Null means stock is not tracked.
    public function availableStock(?ProductVariant $variant = null): ?int
    {
        if (! $this->track_stock) {
            return null;
        }

        if ($variant !== null) {
            return max(0, $variant->stock);
        }

        return $this->hasVariants()
            ? max(0, (int) $this->variants->sum('stock'))
            : max(0, $this->stock);
    }

    public function inStock(?ProductVariant $variant = null): bool
    {
        $stock = $this->availableStock($variant);

        return $stock === null || $stock > 0;
    }

    // The SKU of the variant, or of the product.
    public function skuFor(?ProductVariant $variant = null): ?string
    {
        return $variant->sku ?? $this->sku;
    }

    /**
     * Gallery media ids, in order.
     *
     * @return list<int>
     */
    public function galleryIds(): array
    {
        return array_values(array_filter(array_map(intval(...), (array) $this->gallery), fn (int $id): bool => $id > 0));
    }

    /**
     * The gallery media that still exist, in the saved order.
     *
     * @return list<Media>
     */
    public function galleryMedia(): array
    {
        $ids = $this->galleryIds();

        if ($ids === []) {
            return [];
        }

        $media = Media::query()->whereIn('id', $ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (int $id): ?Media => $media->get($id), $ids)));
    }
}
