<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ShopFixtures;

beforeEach(fn () => ShopFixtures::boot());

function shopBlock(string $type, array $attrs): array
{
    return ['id' => 'b_'.substr(md5($type.json_encode($attrs)), 0, 6), 'type' => $type, 'attrs' => $attrs];
}

it('describes both blocks for the editor', function () {
    $product = app(PluginBlockRegistry::class)->find('shop-product');
    $grid = app(PluginBlockRegistry::class)->find('shop-product-grid');

    expect($product->label())->toBe('Product')
        ->and(array_map(fn ($field) => $field->name, $product->fields()))->toBe(['product', 'showButton'])
        ->and($grid->label())->toBe('Product grid')
        ->and(array_map(fn ($field) => $field->name, $grid->fields()))->toBe(['limit', 'columns', 'sort', 'category']);
});

it('renders one product by slug with a buy button', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);

    $html = app(BlockRenderer::class)->render([shopBlock('shop-product', ['product' => 'stoneware-mug', 'showButton' => true])]);

    expect($html)->toContain('shop-block-product')
        ->toContain('Stoneware Mug')
        ->toContain('€22.00')
        ->toContain('Add to cart')
        ->toContain('name="product_id" value="'.$mug->id.'"');
});

it('finds the product by entry id too', function () {
    $mug = ShopFixtures::product('Stoneware Mug', 2200);

    expect(app(BlockRenderer::class)->render([shopBlock('shop-product', ['product' => (string) $mug->entry_id])]))->toContain('Stoneware Mug');
});

it('can hide the buy button', function () {
    ShopFixtures::product('Stoneware Mug', 2200);

    expect(app(BlockRenderer::class)->render([shopBlock('shop-product', ['product' => 'stoneware-mug', 'showButton' => false])]))
        ->toContain('Stoneware Mug')
        ->not->toContain('Add to cart');
});

it('renders nothing for missing or unpublished products', function () {
    ShopFixtures::product('Draft Planter', 3000, published: false);

    expect(app(BlockRenderer::class)->render([shopBlock('shop-product', ['product' => 'draft-planter'])]))->toBe('')
        ->and(app(BlockRenderer::class)->render([shopBlock('shop-product', ['product' => 'nothing-here'])]))->toBe('')
        ->and(app(BlockRenderer::class)->render([shopBlock('shop-product', [])]))->toBe('');
});

it('shows options and from prices for products with variants', function () {
    ShopFixtures::product('Merino Beanie', 2800, ['track_stock' => true], [['Colour', 'Charcoal', null, 4], ['Colour', 'Saffron', 3200, 0]]);

    $html = app(BlockRenderer::class)->render([shopBlock('shop-product', ['product' => 'merino-beanie'])]);

    expect($html)->toContain('From €28.00')
        ->toContain('<label for="shop-add-')
        ->toContain('Colour')
        ->toContain('Charcoal · €28.00')
        ->toContain('Saffron · €32.00')
        ->toContain('Out of stock');
});

it('renders a grid with the newest products first', function () {
    ShopFixtures::product('Stoneware Mug', 2200)->entry->forceFill(['published_at' => now()->subDays(3)])->save();
    ShopFixtures::product('Ceramic Vase', 4500)->entry->forceFill(['published_at' => now()->subDay()])->save();
    ShopFixtures::product('Draft Planter', 3000, published: false);

    $html = app(BlockRenderer::class)->render([shopBlock('shop-product-grid', ['limit' => 6, 'columns' => '4'])]);

    expect($html)->toContain('shop-grid--4')
        ->not->toContain('Draft Planter');
    expect(strpos($html, 'Ceramic Vase'))->toBeLessThan(strpos($html, 'Stoneware Mug'));
});

it('sorts and limits the grid', function () {
    ShopFixtures::product('Stoneware Mug', 2200);
    ShopFixtures::product('Ceramic Vase', 4500);
    ShopFixtures::product('Olive Wood Spoon', 1450);

    $cheapest = app(BlockRenderer::class)->render([shopBlock('shop-product-grid', ['limit' => 2, 'sort' => 'price_asc'])]);
    $byName = app(BlockRenderer::class)->render([shopBlock('shop-product-grid', ['sort' => 'title'])]);

    expect($cheapest)->toContain('Olive Wood Spoon')->toContain('Stoneware Mug')->not->toContain('Ceramic Vase')
        ->and(strpos($byName, 'Ceramic Vase'))->toBeLessThan(strpos($byName, 'Olive Wood Spoon'));
});

it('keeps grid attrs within safe limits', function () {
    config(['shop.grid_max' => 2]);
    foreach (['Stoneware Mug', 'Ceramic Vase', 'Olive Wood Spoon'] as $title) {
        ShopFixtures::product($title, 1500);
    }

    $html = app(BlockRenderer::class)->render([shopBlock('shop-product-grid', ['limit' => 50, 'columns' => '9', 'sort' => 'random'])]);

    expect(substr_count($html, 'class="shop-card"'))->toBe(2)
        ->and($html)->toContain('shop-grid--3');
});

it('renders an empty grid as nothing', function () {
    expect(app(BlockRenderer::class)->render([shopBlock('shop-product-grid', [])]))->toBe('');
});

it('shows the blocks on a public page', function () {
    ShopFixtures::product('Stoneware Mug', 2200);
    Entry::factory()->page()->published()->create([
        'title' => 'Gifts for the Kitchen',
        'slug' => 'gifts-for-the-kitchen',
        'blocks' => [shopBlock('shop-product-grid', ['limit' => 3])],
    ]);

    $this->get('/gifts-for-the-kitchen')->assertOk()->assertSee('Stoneware Mug')->assertSee('.shop-grid', false);
});

it('offers the blocks in the entry editor', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.create', ['contentType' => 'page']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pluginBlocks', fn ($blocks) => collect($blocks)->pluck('name')->contains('shop-product')
                && collect($blocks)->pluck('name')->contains('shop-product-grid')));
});

it('checks block attrs when an entry is saved', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.entries.store', ['contentType' => 'page']), [
            'title' => 'Gifts for the Kitchen',
            'status' => 'draft',
            'blocks' => [shopBlock('shop-product-grid', ['limit' => 500, 'columns' => '7'])],
        ])
        ->assertSessionHasErrors();
});
