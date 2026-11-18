<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create();
});

it('lists terms with entry counts', function () {
    $travel = Term::factory()->create(['name' => 'Travel']);
    Term::factory()->create(['name' => 'Food']);
    Term::factory()->tag()->create(['name' => 'Porto']);
    Entry::factory()->count(2)->create()->each(fn ($entry) => $entry->terms()->attach($travel));

    $this->actingAs($this->editor)
        ->get(route('admin.terms.index', 'category'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/terms/index')
            ->where('taxonomy.name', 'category')
            ->has('terms.data', 2)
            ->where('terms.data.0.name', 'Food')
            ->where('terms.data.1.entries_count', 2)
            ->where('terms.data.1.url', url('/category/'.$travel->slug))
            ->has('parents', 2)
        );
});

it('pages through terms with the same name without repeating any', function () {
    config(['verso.admin.per_page' => 2]);
    $ids = collect(['porto', 'porto-ribeira', 'porto-wine', 'porto-food'])
        ->map(fn (string $slug) => Term::factory()->create(['name' => 'Porto', 'slug' => $slug])->id);

    $seen = collect([1, 2])->flatMap(function (int $page) {
        $ids = [];

        $this->actingAs($this->editor)
            ->get(route('admin.terms.index', ['taxonomy' => 'category', 'page' => $page]))
            ->assertInertia(function (Assert $inertia) use (&$ids) {
                $inertia->has('terms.data', 2);
                $ids = array_column($inertia->toArray()['props']['terms']['data'], 'id');
            });

        return $ids;
    });

    expect($seen->all())->toBe($ids->all());
});

it('searches terms', function () {
    Term::factory()->create(['name' => 'Gardening']);
    Term::factory()->create(['name' => 'Photography']);

    $this->actingAs($this->editor)
        ->get(route('admin.terms.index', ['taxonomy' => 'category', 'search' => 'garden']))
        ->assertInertia(fn (Assert $page) => $page->has('terms.data', 1));
});

it('sends no parents for tags', function () {
    Term::factory()->tag()->create();

    $this->actingAs($this->editor)
        ->get(route('admin.terms.index', 'tag'))
        ->assertInertia(fn (Assert $page) => $page->where('parents', []));
});

it('returns 404 for unknown taxonomies', function () {
    $this->actingAs($this->editor)->get('/admin/terms/season')->assertNotFound();
});

it('forbids authors', function () {
    $author = User::factory()->author()->create();
    $term = Term::factory()->create();

    $this->actingAs($author)->get(route('admin.terms.index', 'category'))->assertForbidden();
    $this->actingAs($author)->post(route('admin.terms.store', 'category'), ['name' => 'Hacks'])->assertForbidden();
    $this->actingAs($author)->delete(route('admin.terms.destroy', ['taxonomy' => 'category', 'term' => $term->id]))->assertForbidden();
});

it('creates a term', function () {
    $travel = Term::factory()->create();

    $this->actingAs($this->editor)
        ->post(route('admin.terms.store', 'category'), ['name' => 'Europe', 'description' => 'City trips and slow travel.', 'parent_id' => $travel->id])
        ->assertRedirect();

    $europe = Term::firstWhere('name', 'Europe');
    expect($europe->slug)->toBe('europe')
        ->and($europe->parent_id)->toBe($travel->id);
});

it('validates the term form', function () {
    $tag = Term::factory()->tag()->create();

    $this->actingAs($this->editor)
        ->post(route('admin.terms.store', 'category'), ['name' => '', 'parent_id' => $tag->id])
        ->assertSessionHasErrors(['name', 'parent_id']);

    $this->actingAs($this->editor)
        ->post(route('admin.terms.store', 'tag'), ['name' => 'Ribeira', 'parent_id' => $tag->id])
        ->assertSessionHasErrors('parent_id');
});

it('updates a term', function () {
    $term = Term::factory()->create(['name' => 'Food', 'slug' => 'food']);

    $this->actingAs($this->editor)
        ->put(route('admin.terms.update', ['taxonomy' => 'category', 'term' => $term->id]), ['name' => 'Food and Drink', 'slug' => 'food-and-drink'])
        ->assertRedirect();

    expect($term->fresh()->slug)->toBe('food-and-drink');
});

it('refuses a parent loop on update', function () {
    $travel = Term::factory()->create();
    $europe = Term::factory()->create(['parent_id' => $travel->id]);

    $this->actingAs($this->editor)
        ->put(route('admin.terms.update', ['taxonomy' => 'category', 'term' => $travel->id]), ['name' => 'Travel', 'parent_id' => $europe->id])
        ->assertSessionHasErrors('parent_id');
});

it('returns 404 when the term is in another taxonomy', function () {
    $tag = Term::factory()->tag()->create();

    $this->actingAs($this->editor)
        ->put(route('admin.terms.update', ['taxonomy' => 'category', 'term' => $tag->id]), ['name' => 'Moved'])
        ->assertNotFound();
});

it('deletes a term', function () {
    $term = Term::factory()->create();

    $this->actingAs($this->editor)
        ->delete(route('admin.terms.destroy', ['taxonomy' => 'category', 'term' => $term->id]))
        ->assertRedirect();

    expect(Term::find($term->id))->toBeNull();
});
