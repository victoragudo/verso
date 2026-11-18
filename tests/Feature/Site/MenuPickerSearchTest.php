<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function menuSearch(array $query): string
{
    return route('admin.site.menus.search', $query);
}

it('finds published entries of a type by title', function () {
    Entry::factory()->published()->create(['title' => 'Cooking Paella for Twelve People']);
    Entry::factory()->published()->create(['title' => 'A Weekend in Porto']);
    Entry::factory()->create(['title' => 'Draft paella notes']);
    Entry::factory()->page()->published()->create(['title' => 'Paella Workshop']);

    $this->actingAs($this->admin)
        ->getJson(menuSearch(['kind' => 'entry', 'source' => 'post', 'q' => 'paella']))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Cooking Paella for Twelve People')
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('hasMore', false);
});

it('finds terms of a taxonomy by name', function () {
    Term::factory()->create(['name' => 'Mountain Hiking']);
    Term::factory()->create(['name' => 'City Breaks']);
    Term::factory()->tag()->create(['name' => 'Hiking Boots']);

    $this->actingAs($this->admin)
        ->getJson(menuSearch(['kind' => 'term', 'source' => 'category', 'q' => 'hiking']))
        ->assertOk()
        ->assertExactJson([
            'items' => [['id' => Term::query()->where('name', 'Mountain Hiking')->value('id'), 'title' => 'Mountain Hiking']],
            'hasMore' => false,
        ]);
});

it('returns the first items sorted by title when there is no query', function () {
    Entry::factory()->published()->create(['title' => 'Zucchini Bread']);
    Entry::factory()->published()->create(['title' => 'Apple Tart']);

    $this->actingAs($this->admin)
        ->getJson(menuSearch(['kind' => 'entry', 'source' => 'post']))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Apple Tart')
        ->assertJsonPath('items.1.title', 'Zucchini Bread');
});

it('limits search results and tells when more exist', function () {
    config(['verso.menus.picker_limit' => 2]);
    foreach (['Lisbon by Tram', 'Lisbon at Night', 'Lisbon Food Markets'] as $title) {
        Entry::factory()->published()->create(['title' => $title]);
    }

    $this->actingAs($this->admin)
        ->getJson(menuSearch(['kind' => 'entry', 'source' => 'post', 'q' => 'lisbon']))
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('hasMore', true);
});

it('finds items that are beyond the first page of the picker', function () {
    config(['verso.menus.picker_limit' => 2]);
    foreach (['Autumn Soups', 'Baking Rye Bread', 'Winter Stews'] as $title) {
        Entry::factory()->published()->create(['title' => $title]);
    }

    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sources.entries.0.items', 2)
            ->where('sources.entries.0.hasMore', true)
            ->where('sources.entries.0.kind', 'entry')
            ->where('sources.terms.0.kind', 'term')
            ->where('sources.terms.0.hasMore', false)
        );

    $this->actingAs($this->admin)
        ->getJson(menuSearch(['kind' => 'entry', 'source' => 'post', 'q' => 'stews']))
        ->assertJsonPath('items.0.title', 'Winter Stews');
});

it('validates the search request', function (array $query, string $field) {
    $this->actingAs($this->admin)
        ->getJson(menuSearch($query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing kind' => [['source' => 'post'], 'kind'],
    'unknown kind' => [['kind' => 'author', 'source' => 'post'], 'kind'],
    'missing source' => [['kind' => 'entry'], 'source'],
]);

it('returns 404 for unknown content types and taxonomies', function () {
    $this->actingAs($this->admin)->getJson(menuSearch(['kind' => 'entry', 'source' => 'recipe']))->assertNotFound();
    $this->actingAs($this->admin)->getJson(menuSearch(['kind' => 'term', 'source' => 'genre']))->assertNotFound();
});

it('forbids users who cannot manage themes', function () {
    $this->actingAs(User::factory()->author()->create())
        ->getJson(menuSearch(['kind' => 'entry', 'source' => 'post']))
        ->assertForbidden();
});
