<?php

namespace Verso\Plugins\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Core\Enums\Capability;
use App\Verso\Media\Models\Media;
use App\Verso\Shortcodes\ShortcodeRegistry;
use App\Verso\Shortcodes\Usage\UsageIndex;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Shop\Actions\SaveProduct;
use Verso\Plugins\Shop\Blocks\ProductBlock;
use Verso\Plugins\Shop\Http\Requests\ProductRequest;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Models\ProductVariant;
use Verso\Plugins\Shop\Support\Money;
use Verso\Plugins\Shop\Support\ShopSettings;

// Admin pages for the shop data of products: price, SKU, stock, gallery and variants.
class ProductController extends Controller
{
    public function __construct(
        protected Money $money,
        protected ShopSettings $settings,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        Gate::authorize(Capability::ManageShop->value);

        $search = trim((string) $request->query('search', ''));
        $currency = $this->settings->currency();

        $entries = Entry::query()
            ->ofType(Product::TYPE)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => TextSearch::apply($query, ['title'], $search)
                ->orWhereIn('id', TextSearch::apply(Product::query(), ['sku'], $search)->select('entry_id'))))
            ->orderBy('title')
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString();

        $products = Product::query()
            ->with('variants')
            ->whereIn('entry_id', $entries->getCollection()->pluck('id'))
            ->get()
            ->keyBy('entry_id');

        return Inertia::render('admin/plugins/shop/products/index', [
            'products' => $entries->through(function (Entry $entry) use ($products, $currency): array {
                /** @var Product|null $product */
                $product = $products->get($entry->id);

                return [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'status' => $entry->status->value,
                    'updated_at' => $entry->updated_at?->toIso8601String(),
                    'price' => $product === null ? null : $this->priceRange($product, $currency),
                    'sku' => $product?->sku,
                    'stock' => $product?->availableStock(),
                    'tracksStock' => (bool) $product?->track_stock,
                    'variants' => $product?->variants->count() ?? 0,
                    'hasShopData' => $product !== null,
                    'editUrl' => route('admin.shop.products.edit', $entry),
                    'contentUrl' => route('admin.entries.edit', ['contentType' => Product::TYPE, 'entry' => $entry]),
                    'viewUrl' => $entry->isPublished() ? $entry->url() : null,
                ];
            }),
            'filters' => ['search' => $search],
            'createUrl' => route('admin.entries.create', ['contentType' => Product::TYPE]),
            'indexUrl' => route('admin.shop.products.index'),
            'currency' => $currency,
            'listView' => AdminPreferences::of($user)->listViewProps('shop.products'),
        ]);
    }

    public function edit(Entry $entry): Response
    {
        Gate::authorize(Capability::ManageShop->value);
        abort_unless($entry->type === Product::TYPE, 404);

        $product = Product::forEntry($entry)->loadMissing('variants');
        $currency = $this->settings->currency();

        return Inertia::render('admin/plugins/shop/products/edit', [
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'status' => $entry->status->value,
                'contentUrl' => route('admin.entries.edit', ['contentType' => Product::TYPE, 'entry' => $entry]),
                'viewUrl' => $entry->isPublished() ? $entry->url() : null,
            ],
            'embed' => [
                'shortcode' => app(ShortcodeRegistry::class)->snippet(ProductBlock::NAME, ['product' => $entry->slug]),
                'blade' => app(ShortcodeRegistry::class)->bladeSnippet(ProductBlock::NAME, ['product' => $entry->slug]),
                'usedIn' => app(UsageIndex::class)->usedIn(ProductBlock::NAME, [(string) $entry->slug, (string) $entry->id], (int) config('verso.shortcodes.used_in_limit')),
            ],
            'product' => [
                'price' => $this->money->toDecimal($product->price, $currency),
                'sku' => $product->sku ?? '',
                'track_stock' => $product->track_stock,
                'stock' => $product->stock,
                'gallery' => array_map(fn (Media $media): array => [
                    'id' => $media->id,
                    'url' => $media->url(),
                    'thumbnail' => $media->isResizable() ? $media->imageUrl(['w' => 240, 'h' => 240, 'fit' => 'crop']) : $media->url(),
                    'alt' => (string) $media->alt,
                    'filename' => $media->filename,
                ], $product->galleryMedia()),
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'option_name' => $variant->option_name,
                    'option_value' => $variant->option_value,
                    'sku' => $variant->sku ?? '',
                    'price' => $variant->price === null ? '' : $this->money->toDecimal($variant->price, $currency),
                    'stock' => $variant->stock,
                ])->values()->all(),
            ],
            'currency' => $currency,
            'updateUrl' => route('admin.shop.products.update', $entry),
            'indexUrl' => route('admin.shop.products.index'),
        ]);
    }

    public function update(ProductRequest $request, Entry $entry, SaveProduct $save): RedirectResponse
    {
        Gate::authorize(Capability::ManageShop->value);
        abort_unless($entry->type === Product::TYPE, 404);

        $save->handle($entry, $request->productData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shop::Product saved.')]);

        return to_route('admin.shop.products.edit', $entry);
    }

    /**
     * @return array{low: float, high: float}
     */
    protected function priceRange(Product $product, string $currency): array
    {
        [$low, $high] = $product->priceRange();

        return ['low' => $this->money->toMajor($low, $currency), 'high' => $this->money->toMajor($high, $currency)];
    }
}
