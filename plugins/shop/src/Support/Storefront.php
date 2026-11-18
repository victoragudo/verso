<?php

namespace Verso\Plugins\Shop\Support;

use App\Verso\Content\Models\Term;
use App\Verso\Media\ImageUrl;
use App\Verso\Media\Models\Media;
use App\Verso\Media\ResponsiveImages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Verso\Plugins\Shop\Models\Product;

// Data the public shop views need: product lists, price labels and images.
class Storefront
{
    // Sort orders for product lists.
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'title'];

    public function __construct(
        protected Money $money,
        protected ShopSettings $settings,
        protected ResponsiveImages $images,
        protected ImageUrl $imageUrls,
    ) {}

    /**
     * A page of products for the shop page.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(string $sort, int $perPage, ?Term $category = null): LengthAwarePaginator
    {
        return $this->query($sort, $category)->paginate(max(1, $perPage))->withQueryString();
    }

    /**
     * The first products for a product grid.
     *
     * @return Collection<int, Product>
     */
    public function take(string $sort, int $limit, ?Term $category = null): Collection
    {
        return $this->query($sort, $category)->limit(max(1, $limit))->get();
    }

    // A product category by slug, or null.
    public function category(string $slug): ?Term
    {
        $slug = trim($slug);

        return $slug === '' ? null : Term::query()->inTaxonomy(Product::CATEGORY_TAXONOMY)->where('slug', $slug)->first();
    }

    /**
     * Every product category, sorted by name.
     *
     * @return Collection<int, Term>
     */
    public function categories(): Collection
    {
        return Term::query()->inTaxonomy(Product::CATEGORY_TAXONOMY)->orderBy('name')->orderBy('id')->get();
    }

    /**
     * The category and all categories below it, so a parent category also lists the products of its children.
     *
     * @return list<int>
     */
    public function categoryIds(Term $category): array
    {
        $ids = [$category->id];
        $level = [$category->id];

        while ($level !== []) {
            $level = Term::query()
                ->inTaxonomy(Product::CATEGORY_TAXONOMY)
                ->whereIn('parent_id', $level)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    // One sellable product by its entry slug or id.
    public function find(string $slugOrId): ?Product
    {
        $slugOrId = trim($slugOrId);

        if ($slugOrId === '') {
            return null;
        }

        return Product::query()
            ->sellable()
            ->with(['entry', 'variants'])
            ->whereHas('entry', fn (Builder $entries) => ctype_digit($slugOrId)
                ? $entries->whereKey((int) $slugOrId)
                : $entries->where('slug', $slugOrId))
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public function sortLabels(): array
    {
        return [
            'newest' => __('shop::Newest first'),
            'price_asc' => __('shop::Price, low to high'),
            'price_desc' => __('shop::Price, high to low'),
            'title' => __('shop::Name, A to Z'),
        ];
    }

    // The price text: "€24.00", or "From €18.00" when variants cost different amounts.
    public function priceLabel(Product $product): string
    {
        [$low, $high] = $product->priceRange();
        $price = $this->money->format($low, $this->currency());

        return $low === $high ? $price : __('shop::From :price', ['price' => $price]);
    }

    // A price in the shop currency, or in the given one (like the currency of an old order).
    public function format(int $minor, ?string $currency = null): string
    {
        return $this->money->format($minor, $currency ?? $this->currency());
    }

    public function currency(): string
    {
        return $this->settings->currency();
    }

    /**
     * The main image: the featured image of the entry, or the first gallery image.
     *
     * @return array{src: string, srcset: string, sizes: string, width: int|null, height: int|null, alt: string}|null
     */
    public function image(Product $product, string $sizes): ?array
    {
        $featured = $product->entry->meta['featured_image']['mediaId'] ?? null;

        return $this->images->attributes($featured, $sizes)
            ?? $this->images->attributes($product->galleryIds()[0] ?? null, $sizes);
    }

    /**
     * Gallery images with srcset, for the product page.
     *
     * @return list<array{src: string, srcset: string, sizes: string, width: int|null, height: int|null, alt: string}>
     */
    public function gallery(Product $product, string $sizes): array
    {
        return array_values(array_map(
            fn (Media $media): array => $this->imageUrls->attributes($media, $sizes),
            array_filter($product->galleryMedia(), fn (Media $media): bool => $media->isImage()),
        ));
    }

    /** @return Builder<Product> */
    protected function query(string $sort, ?Term $category = null): Builder
    {
        $query = Product::query()
            ->sellable()
            ->with(['entry', 'variants'])
            ->select('shop_products.*')
            ->join('entries', 'entries.id', '=', 'shop_products.entry_id');

        if ($category !== null) {
            $ids = $this->categoryIds($category);
            $query->whereHas('entry.terms', fn (Builder $terms) => $terms->whereIn('terms.id', $ids));
        }

        return match ($sort) {
            'price_asc' => $query->orderBy('shop_products.price')->orderBy('entries.title'),
            'price_desc' => $query->orderByDesc('shop_products.price')->orderBy('entries.title'),
            'title' => $query->orderBy('entries.title'),
            default => $query->orderByDesc('entries.published_at')->orderByDesc('shop_products.id'),
        };
    }
}
