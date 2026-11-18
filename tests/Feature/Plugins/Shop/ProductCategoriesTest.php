<?php

use App\Models\User;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Blocks\ProductGridBlock;
use Verso\Plugins\Shop\Models\Product;
use Verso\Plugins\Shop\Support\Storefront;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    ShopFixtures::boot();

    $this->kitchen = Term::factory()->create(['taxonomy' => 'product_category', 'name' => 'Kitchen', 'slug' => 'kitchen']);
    $this->mugs = Term::factory()->create(['taxonomy' => 'product_category', 'name' => 'Mugs', 'slug' => 'mugs', 'parent_id' => $this->kitchen->id]);
    $this->garden = Term::factory()->create(['taxonomy' => 'product_category', 'name' => 'Garden', 'slug' => 'garden', 'description' => 'Pots and tools for small balconies.']);

    $this->mug = ShopFixtures::product('Stoneware Mug', 2200);
    $this->kettle = ShopFixtures::product('Enamel Kettle', 4500);
    $this->planter = ShopFixtures::product('Terracotta Planter', 3100);

    $this->mug->entry->terms()->attach($this->mugs);
    $this->kettle->entry->terms()->attach($this->kitchen);
    $this->planter->entry->terms()->attach($this->garden);
});

function gridBlock(array $attrs): array
{
    return ['id' => 'b_grid'.substr(md5(json_encode($attrs)), 0, 4), 'type' => ProductGridBlock::NAME, 'attrs' => $attrs];
}

it('registers a hierarchical product category taxonomy for products', function () {
    $taxonomy = app(TaxonomyRegistry::class)->find('product_category');

    expect($taxonomy)->not->toBeNull()
        ->and($taxonomy->hierarchical)->toBeTrue()
        ->and($taxonomy->appliesTo(Product::TYPE))->toBeTrue()
        ->and($taxonomy->pathFor('mugs'))->toBe('/product-category/mugs')
        ->and(app(ContentTypeRegistry::class)->get(Product::TYPE)->taxonomies)->toBe(['product_category']);
});

it('reserves the category path so pages cannot take it', function () {
    expect(config('verso.content.reserved_slugs'))->toContain('product-category');
});

it('shows a category archive with its products and those of its sub categories', function () {
    $this->get('/product-category/kitchen')
        ->assertOk()
        ->assertSee('Kitchen')
        ->assertSee('Enamel Kettle')
        ->assertSee('Stoneware Mug')
        ->assertDontSee('Terracotta Planter')
        ->assertSee('href="/product-category/mugs"', false);
});

it('shows only the products of a leaf category', function () {
    $this->get('/product-category/mugs')
        ->assertOk()
        ->assertSee('Stoneware Mug')
        ->assertDontSee('Enamel Kettle');
});

it('shows the category description and a sort form', function () {
    $this->get('/product-category/garden')
        ->assertOk()
        ->assertSee('Pots and tools for small balconies.')
        ->assertSee('action="/product-category/garden"', false)
        ->assertSee('Price, low to high');
});

it('sorts category products by price', function () {
    $html = $this->get('/product-category/kitchen?sort=price_asc')->assertOk()->getContent();

    expect(strpos($html, 'Stoneware Mug'))->toBeLessThan(strpos($html, 'Enamel Kettle'));
});

it('leaves out unpublished products and shows an empty message', function () {
    $draft = ShopFixtures::product('Draft Watering Can', 2800, published: false);
    $empty = Term::factory()->create(['taxonomy' => 'product_category', 'name' => 'Lighting', 'slug' => 'lighting']);
    $draft->entry->terms()->attach($empty);

    $this->get('/product-category/lighting')
        ->assertOk()
        ->assertSee('There are no products in Lighting yet.')
        ->assertDontSee('Draft Watering Can');
});

it('answers 404 for unknown categories and pages past the end', function () {
    $this->get('/product-category/umbrellas')->assertNotFound();
    $this->get('/product-category/garden?page=5')->assertNotFound();
});

it('lists top level categories on the shop page', function () {
    $this->get('/shop')
        ->assertOk()
        ->assertSee('href="/product-category/kitchen"', false)
        ->assertSee('href="/product-category/garden"', false)
        ->assertDontSee('href="/product-category/mugs"', false);
});

it('offers the categories in the product grid block', function () {
    $grid = app(PluginBlockRegistry::class)->find(ProductGridBlock::NAME);
    $field = collect($grid->fields())->firstWhere('name', 'category');

    expect($field->options)->toBe([
        ProductGridBlock::ALL_CATEGORIES => 'All categories',
        'garden' => 'Garden',
        'kitchen' => 'Kitchen',
        'mugs' => 'Mugs',
    ])->and($field->default)->toBe(ProductGridBlock::ALL_CATEGORIES);
});

it('filters the product grid block by category', function () {
    $html = app(BlockRenderer::class)->render([gridBlock(['limit' => 6, 'category' => 'kitchen'])]);

    expect($html)->toContain('Enamel Kettle')
        ->toContain('Stoneware Mug')
        ->not->toContain('Terracotta Planter');
});

it('shows every product when the grid uses all categories', function () {
    $html = app(BlockRenderer::class)->render([gridBlock(['limit' => 6, 'category' => ProductGridBlock::ALL_CATEGORIES])]);

    expect($html)->toContain('Enamel Kettle')->toContain('Terracotta Planter');
});

it('renders nothing for a grid with a deleted category', function () {
    expect(app(BlockRenderer::class)->render([gridBlock(['category' => 'umbrellas'])]))->toBe('');
});

it('accepts only known categories when the entry is saved', function () {
    $field = collect(app(PluginBlockRegistry::class)->find(ProductGridBlock::NAME)->fields())->firstWhere('name', 'category');

    expect(validator(['category' => 'garden'], ['category' => $field->rules()])->passes())->toBeTrue()
        ->and(validator(['category' => 'umbrellas'], ['category' => $field->rules()])->passes())->toBeFalse();
});

it('collects the ids of a category and everything below it', function () {
    $cups = Term::factory()->create(['taxonomy' => 'product_category', 'name' => 'Espresso cups', 'slug' => 'espresso-cups', 'parent_id' => $this->mugs->id]);

    expect(app(Storefront::class)->categoryIds($this->kitchen))
        ->toEqualCanonicalizing([$this->kitchen->id, $this->mugs->id, $cups->id]);
});

it('lets editors assign product categories in the admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/terms/product_category')
        ->assertOk();
});
