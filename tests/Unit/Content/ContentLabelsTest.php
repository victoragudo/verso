<?php

use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Taxonomy;

beforeEach(function () {
    app('translator')->addLines([
        '*.Posts' => 'Entradas',
        '*.Post' => 'Entrada',
        '*.Categories' => 'Categorías',
        '*.shop::Products' => 'Productos de tienda',
        '*.Products' => 'Productos',
        '*.shop::Product categories' => 'Categorías de productos',
    ], 'es');
    app()->setLocale('es');
});

it('translates core content type labels', function () {
    $labels = app(ContentLabels::class);
    $post = app(ContentTypeRegistry::class)->get('post');

    expect($labels->typeLabel($post))->toBe('Entrada')
        ->and($labels->typeArray($post))->toMatchArray(['name' => 'post', 'label' => 'Entrada', 'pluralLabel' => 'Entradas']);
});

it('prefers the plugin key for content types and taxonomies of a plugin', function () {
    $registry = app(ContentTypeRegistry::class);
    $registry->register(new ContentType(name: 'product', label: 'Product', pluralLabel: 'Products'), 'shop');
    $labels = new ContentLabels($registry);
    $taxonomy = new Taxonomy(name: 'product_category', label: 'Product category', pluralLabel: 'Product categories', contentTypes: ['product']);

    expect($labels->typePluralLabel($registry->get('product')))->toBe('Productos de tienda')
        ->and($labels->taxonomyPluralLabel($taxonomy))->toBe('Categorías de productos')
        ->and($labels->taxonomyArray($taxonomy)['label'])->toBe('Product category');
});

it('lowercases labels inside sentences except in languages that capitalize nouns', function () {
    $labels = app(ContentLabels::class);

    expect($labels->inline('Categorías'))->toBe('categorías');

    app()->setLocale('de');

    expect($labels->inline('Beiträge'))->toBe('Beiträge');
});
