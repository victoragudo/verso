<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Models\ProductVariant;

beforeEach(function () {
    ShopFixtures::boot();
    $this->admin = User::factory()->admin()->create();
});

function productPayload(array $overrides = []): array
{
    return [
        'price' => '24.90',
        'sku' => 'MUG-STONE',
        'track_stock' => true,
        'stock' => 12,
        'gallery' => [],
        'variants' => [],
        ...$overrides,
    ];
}

it('lists products with price, SKU and stock', function () {
    ShopFixtures::product('Stoneware Mug', 2200, ['sku' => 'MUG-STONE', 'track_stock' => true, 'stock' => 7]);
    ShopFixtures::product('Merino Beanie', 2800, variants: [['Colour', 'Charcoal', null, 1], ['Colour', 'Saffron', 3200, 1]]);
    Entry::factory()->create(['type' => 'product', 'title' => 'Walnut Board']);
    Entry::factory()->create(['type' => 'post', 'title' => 'Spring Market Report']);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/shop/products/index')
            ->has('products.data', 3)
            ->where('products.data.0.title', 'Merino Beanie')
            ->where('products.data.0.price', ['low' => 28, 'high' => 32])
            ->where('products.data.0.variants', 2)
            ->where('products.data.1.title', 'Stoneware Mug')
            ->where('products.data.1.price', ['low' => 22, 'high' => 22])
            ->where('products.data.1.sku', 'MUG-STONE')
            ->where('products.data.1.stock', 7)
            ->where('products.data.1.tracksStock', true)
            ->where('products.data.2.title', 'Walnut Board')
            ->where('products.data.2.price', null)
            ->where('products.data.2.hasShopData', false)
            ->where('currency', 'EUR')
            ->where('createUrl', route('admin.entries.create', ['contentType' => 'product']))
        );
});

it('searches products by name and SKU', function () {
    ShopFixtures::product('Stoneware Mug', 2200, ['sku' => 'MUG-STONE']);
    ShopFixtures::product('Ceramic Vase', 4500, ['sku' => 'VASE-TALL']);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.products.index', ['search' => 'vase-t']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.title', 'Ceramic Vase'));

    $this->actingAs($this->admin)
        ->get(route('admin.shop.products.index', ['search' => 'stoneware']))
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('filters.search', 'stoneware'));
});

it('shows the product form with decimals and variants', function () {
    $photo = Media::factory()->create(['alt' => 'Mug on a linen cloth']);
    $mug = ShopFixtures::product('Stoneware Mug', 2250, ['sku' => 'MUG-STONE', 'gallery' => [$photo->id]], [['Glaze', 'Moss', 2600, 3]]);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.products.edit', $mug->entry))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/shop/products/edit')
            ->where('entry.title', 'Stoneware Mug')
            ->where('product.price', '22.50')
            ->where('product.sku', 'MUG-STONE')
            ->where('product.gallery.0.id', $photo->id)
            ->where('product.gallery.0.alt', 'Mug on a linen cloth')
            ->where('product.variants.0.option_name', 'Glaze')
            ->where('product.variants.0.price', '26.00')
            ->where('product.variants.0.stock', 3)
            ->where('updateUrl', route('admin.shop.products.update', $mug->entry))
        );
});

it('shows an empty form for a product without shop data', function () {
    $entry = Entry::factory()->create(['type' => 'product', 'title' => 'Walnut Board']);

    $this->actingAs($this->admin)
        ->get(route('admin.shop.products.edit', $entry))
        ->assertInertia(fn (Assert $page) => $page->where('product.price', '0.00')->where('product.variants', []));
});

it('only edits product entries', function () {
    $post = Entry::factory()->create(['type' => 'post']);

    $this->actingAs($this->admin)->get(route('admin.shop.products.edit', $post))->assertNotFound();
    $this->actingAs($this->admin)->put(route('admin.shop.products.update', $post), productPayload())->assertNotFound();
});

it('saves price, SKU, stock and gallery', function () {
    $entry = Entry::factory()->published()->create(['type' => 'product', 'title' => 'Stoneware Mug']);
    $photos = Media::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.shop.products.update', $entry), productPayload(['gallery' => [$photos[1]->id, $photos[0]->id]]))
        ->assertRedirect(route('admin.shop.products.edit', $entry));

    $product = Product::query()->where('entry_id', $entry->id)->sole();

    expect($product->price)->toBe(2490)
        ->and($product->sku)->toBe('MUG-STONE')
        ->and($product->track_stock)->toBeTrue()
        ->and($product->stock)->toBe(12)
        ->and($product->galleryIds())->toBe([$photos[1]->id, $photos[0]->id]);
});

it('adds, changes and removes variants', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900, variants: [['Size', 'Small', null, 4], ['Size', 'Medium', null, 2]]);
    [$small, $medium] = $tote->variants->all();

    $this->actingAs($this->admin)->put(route('admin.shop.products.update', $tote->entry), productPayload([
        'price' => '19',
        'sku' => '',
        'variants' => [
            ['id' => $small->id, 'option_name' => 'Size', 'option_value' => 'Small', 'sku' => 'TOTE-S', 'price' => '', 'stock' => 9],
            ['id' => null, 'option_name' => 'Size', 'option_value' => 'Large', 'sku' => 'TOTE-L', 'price' => '24.50', 'stock' => 3],
        ],
    ]))->assertSessionHasNoErrors();

    $variants = ProductVariant::query()->orderBy('position')->get();

    expect($variants->pluck('option_value')->all())->toBe(['Small', 'Large'])
        ->and($variants[0]->id)->toBe($small->id)
        ->and($variants[0]->stock)->toBe(9)
        ->and($variants[0]->price)->toBeNull()
        ->and($variants[1]->price)->toBe(2450)
        ->and($variants[1]->position)->toBe(1)
        ->and(ProductVariant::query()->find($medium->id))->toBeNull()
        ->and($tote->fresh()->sku)->toBeNull();
});

it('does not take variants of another product', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900);
    $beanie = ShopFixtures::product('Merino Beanie', 2800, variants: [['Colour', 'Saffron', null, 5]]);

    $this->actingAs($this->admin)->put(route('admin.shop.products.update', $tote->entry), productPayload([
        'variants' => [['id' => $beanie->variants->first()->id, 'option_name' => 'Size', 'option_value' => 'Large', 'sku' => '', 'price' => '', 'stock' => 1]],
    ]));

    expect($beanie->variants()->first()->option_value)->toBe('Saffron')
        ->and($tote->variants()->count())->toBe(1);
});

it('checks the product form', function (array $payload, string $field) {
    $entry = Entry::factory()->create(['type' => 'product']);
    ShopFixtures::product('Ceramic Vase', 4500, ['sku' => 'VASE-TALL'], [['Height', 'Tall', null, 1]]);

    $this->actingAs($this->admin)
        ->put(route('admin.shop.products.update', $entry), productPayload($payload))
        ->assertSessionHasErrors($field);
})->with([
    'price with comma' => [['price' => '24,90'], 'price'],
    'negative price' => [['price' => '-5'], 'price'],
    'text price' => [['price' => 'free'], 'price'],
    'taken SKU' => [['sku' => 'VASE-TALL'], 'sku'],
    'negative stock' => [['stock' => -1], 'stock'],
    'missing media' => [['gallery' => [999]], 'gallery.0'],
    'variant without name' => [['variants' => [['option_name' => '', 'option_value' => 'Large', 'stock' => 1]]], 'variants.0.option_name'],
    'variant bad price' => [['variants' => [['option_name' => 'Size', 'option_value' => 'Large', 'price' => '1,5', 'stock' => 1]]], 'variants.0.price'],
    'variant taken SKU' => [['variants' => [['option_name' => 'Size', 'option_value' => 'Large', 'sku' => 'CERAMIC-VASE-TALL', 'stock' => 1]]], 'variants.0.sku'],
    'same option twice' => [['variants' => [
        ['option_name' => 'Size', 'option_value' => 'Large', 'stock' => 1],
        ['option_name' => 'size', 'option_value' => 'large ', 'stock' => 1],
    ]], 'variants.1.option_value'],
]);

it('keeps its own SKUs when saving again', function () {
    $tote = ShopFixtures::product('Canvas Tote', 1900, ['sku' => 'TOTE'], [['Size', 'Small', null, 4]]);
    $small = $tote->variants->first();

    $this->actingAs($this->admin)->put(route('admin.shop.products.update', $tote->entry), productPayload([
        'sku' => 'TOTE',
        'variants' => [['id' => $small->id, 'option_name' => 'Size', 'option_value' => 'Small', 'sku' => $small->sku, 'price' => '', 'stock' => 4]],
    ]))->assertSessionHasNoErrors();
});

it('is only for people who edit any entry', function () {
    $author = User::factory()->author()->create();
    $mug = ShopFixtures::product('Stoneware Mug', 2200);

    $this->actingAs($author)->get(route('admin.shop.products.index'))->assertForbidden();
    $this->actingAs($author)->get(route('admin.shop.products.edit', $mug->entry))->assertForbidden();
    $this->actingAs($author)->put(route('admin.shop.products.update', $mug->entry), productPayload())->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.shop.products.index'))->assertRedirect(route('login'));
});

it('keeps editors out of the inventory, which needs shop.manage', function () {
    $this->actingAs(User::factory()->editor()->create())->get(route('admin.shop.products.index'))->assertForbidden();
});

it('lets shop managers manage products', function () {
    $this->actingAs(User::factory()->shopManager()->create())->get(route('admin.shop.products.index'))->assertOk();
});
