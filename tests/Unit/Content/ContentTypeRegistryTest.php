<?php

use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;

it('registers the built in post and page types', function () {
    $registry = app(ContentTypeRegistry::class);

    expect($registry->names())->toBe(['post', 'page'])
        ->and($registry->get('post')->hasArchive)->toBeTrue()
        ->and($registry->get('post')->hierarchical)->toBeFalse()
        ->and($registry->get('post')->routeBase)->toBe('blog')
        ->and($registry->get('page')->hierarchical)->toBeTrue()
        ->and($registry->get('page')->routeBase)->toBe('');
});

it('is a singleton', function () {
    expect(app(ContentTypeRegistry::class))->toBe(app(ContentTypeRegistry::class));
});

it('lets plugins add types and sorts them by menu order', function () {
    $registry = new ContentTypeRegistry;
    $registry->register(new ContentType(name: 'recipe', label: 'Recipe', pluralLabel: 'Recipes', menuOrder: 30));
    $registry->register(new ContentType(name: 'event', label: 'Event', pluralLabel: 'Events', menuOrder: 5));

    expect($registry->names())->toBe(['event', 'recipe'])
        ->and($registry->has('recipe'))->toBeTrue()
        ->and($registry->find('podcast'))->toBeNull();
});

it('throws for unknown types', function () {
    app(ContentTypeRegistry::class)->get('podcast');
})->throws(InvalidArgumentException::class, 'Unknown content type [podcast].');

it('builds paths from the route base', function () {
    $post = new ContentType(name: 'post', label: 'Post', pluralLabel: 'Posts', routeBase: 'blog');
    $page = new ContentType(name: 'page', label: 'Page', pluralLabel: 'Pages');

    expect($post->pathFor('summer-in-porto'))->toBe('/blog/summer-in-porto')
        ->and($page->pathFor('about/team'))->toBe('/about/team');
});

it('exports itself for the admin UI', function () {
    expect(app(ContentTypeRegistry::class)->get('post')->toArray())->toBe([
        'name' => 'post',
        'label' => 'Post',
        'pluralLabel' => 'Posts',
        'hierarchical' => false,
        'hasArchive' => true,
        'taxonomies' => ['category', 'tag'],
        'icon' => 'posts',
    ]);
});
