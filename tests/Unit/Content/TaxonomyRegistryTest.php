<?php

use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;

it('registers categories and tags for posts', function () {
    $registry = app(TaxonomyRegistry::class);

    expect(array_keys($registry->all()))->toBe(['category', 'tag'])
        ->and($registry->get('category')->hierarchical)->toBeTrue()
        ->and($registry->get('tag')->hierarchical)->toBeFalse()
        ->and(array_keys($registry->forContentType('post')))->toBe(['category', 'tag'])
        ->and($registry->forContentType('page'))->toBe([]);
});

it('lets plugins add taxonomies', function () {
    $registry = new TaxonomyRegistry;
    $registry->register(new Taxonomy(name: 'cuisine', label: 'Cuisine', pluralLabel: 'Cuisines', contentTypes: ['recipe'], routeBase: 'cuisine'));

    expect($registry->has('cuisine'))->toBeTrue()
        ->and($registry->get('cuisine')->appliesTo('recipe'))->toBeTrue()
        ->and($registry->get('cuisine')->appliesTo('post'))->toBeFalse()
        ->and($registry->find('season'))->toBeNull();
});

it('throws for unknown taxonomies', function () {
    app(TaxonomyRegistry::class)->get('season');
})->throws(InvalidArgumentException::class);

it('builds term paths', function () {
    expect(app(TaxonomyRegistry::class)->get('category')->pathFor('travel'))->toBe('/category/travel')
        ->and(app(TaxonomyRegistry::class)->get('tag')->toArray())->toBe([
            'name' => 'tag', 'label' => 'Tag', 'pluralLabel' => 'Tags', 'hierarchical' => false, 'icon' => 'tags',
        ]);
});
