<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\Filters\EntryContentFilter;
use Database\Factories\EntryFactory;

it('lists published posts, newest first', function () {
    $older = Entry::factory()->published()->create(['title' => 'Packing for the Camino', 'published_at' => now()->subDays(10)]);
    $newer = Entry::factory()->published()->create(['title' => 'Night Trains of Europe', 'published_at' => now()->subDay()]);

    $this->getJson('/api/v1/entries/post')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.title', 'Night Trains of Europe')
        ->assertJsonPath('data.0.url', $newer->url())
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', config('verso.api.per_page'))
        ->assertJsonStructure([
            'data' => [['id', 'type', 'title', 'slug', 'path', 'url', 'excerpt', 'published_at', 'author' => ['id', 'name'], 'terms']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);
});

it('hides drafts, scheduled, trashed and future entries and other types', function () {
    $live = Entry::factory()->published()->create();
    Entry::factory()->create();
    Entry::factory()->scheduled()->create();
    Entry::factory()->published()->create()->moveToTrash();
    Entry::factory()->published()->page()->create();

    $this->getJson('/api/v1/entries/post')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $live->id);
});

it('does not send the blocks or HTML in lists', function () {
    Entry::factory()->published()->create();

    $this->getJson('/api/v1/entries/post')
        ->assertJsonMissingPath('data.0.html')
        ->assertJsonMissingPath('data.0.blocks');
});

it('paginates with per_page and keeps the query string', function () {
    Entry::factory()->published()->count(5)->create();

    $this->getJson('/api/v1/entries/post?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('links.next', url('/api/v1/entries/post?per_page=2&page=3'));
});

it('validates the query string', function (string $query, string $field) {
    config(['verso.api.max_per_page' => 50]);

    $this->getJson('/api/v1/entries/post?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    ['per_page=51', 'per_page'],
    ['per_page=0', 'per_page'],
    ['sort=views', 'sort'],
    ['author=marta', 'author'],
    ['page=0', 'page'],
]);

it('searches titles and excerpts', function () {
    Entry::factory()->published()->create(['title' => 'Sourdough for Beginners']);
    Entry::factory()->published()->create(['title' => 'A Week in Crete', 'excerpt' => 'Baking bread in a village oven.']);
    Entry::factory()->published()->create(['title' => 'Cycling the Danube']);

    $this->getJson('/api/v1/entries/post?search=bread')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'A Week in Crete');
    $this->getJson('/api/v1/entries/post?search=sourdough')->assertJsonCount(1, 'data');
});

it('filters by author', function () {
    $lucia = User::factory()->author()->create(['name' => 'Lucía Romero']);
    Entry::factory()->published()->by($lucia)->count(2)->create();
    Entry::factory()->published()->create();

    $this->getJson('/api/v1/entries/post?author='.$lucia->id)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.author.name', 'Lucía Romero');
});

it('filters by taxonomy term slugs', function () {
    $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    $food = Term::factory()->create(['name' => 'Food', 'slug' => 'food']);
    $porto = Term::factory()->tag()->create(['name' => 'Porto', 'slug' => 'porto']);

    $trip = Entry::factory()->published()->create(['title' => 'Two Days in Porto']);
    $trip->terms()->attach([$travel->id, $porto->id]);
    Entry::factory()->published()->create(['title' => 'Bacalhau at Home'])->terms()->attach($food);
    Entry::factory()->published()->create(['title' => 'Untagged Notes']);

    $this->getJson('/api/v1/entries/post?category=travel')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Two Days in Porto');
    $this->getJson('/api/v1/entries/post?category=travel,food')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/entries/post?category[]=food')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/entries/post?category=travel&tag=porto')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/entries/post?tag=lisbon')->assertJsonCount(0, 'data');
});

it('groups terms by taxonomy', function () {
    $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    Entry::factory()->published()->create()->terms()->attach($travel);

    $this->getJson('/api/v1/entries/post')
        ->assertJsonPath('data.0.terms.category.0.name', 'Travel')
        ->assertJsonPath('data.0.terms.category.0.url', url('/category/travel'));
});

it('sorts by title', function () {
    Entry::factory()->published()->create(['title' => 'Mountains']);
    Entry::factory()->published()->create(['title' => 'Bridges']);
    Entry::factory()->published()->create(['title' => 'Rivers']);

    $this->getJson('/api/v1/entries/post?sort=title')->assertJsonPath('data.*.title', ['Bridges', 'Mountains', 'Rivers']);
    $this->getJson('/api/v1/entries/post?sort=-title')->assertJsonPath('data.*.title', ['Rivers', 'Mountains', 'Bridges']);
});

it('filters pages by parent', function () {
    $about = Entry::factory()->page()->published()->create(['title' => 'About', 'slug' => 'about']);
    Entry::factory()->page()->published()->create(['title' => 'Team', 'slug' => 'team', 'parent_id' => $about->id]);
    Entry::factory()->page()->published()->create(['title' => 'Contact', 'slug' => 'contact']);

    $this->getJson('/api/v1/entries/page?parent='.$about->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Team');
});

it('returns 404 for unknown content types', function () {
    $this->getJson('/api/v1/entries/recipe')->assertNotFound();
});

it('shows one published entry with its rendered HTML', function () {
    $entry = Entry::factory()->published()->create([
        'title' => 'Night Trains of Europe',
        'slug' => 'night-trains-of-europe',
        'blocks' => [
            ['id' => 'b_h', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Vienna to Venice'],
            ...EntryFactory::paragraphs('Sleeper cabins are <b>back</b>.'),
        ],
    ]);

    $this->getJson('/api/v1/entries/post/night-trains-of-europe')
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.html', '<h2>Vienna to Venice</h2><p>Sleeper cabins are &lt;b&gt;back&lt;/b&gt;.</p>')
        ->assertJsonCount(2, 'data.blocks');
});

it('applies plugin filters to the single entry', function () {
    Entry::factory()->published()->create(['slug' => 'night-trains-of-europe', 'blocks' => EntryFactory::paragraphs('All aboard.')]);
    Filters::add(EntryContentFilter::class, fn (string $html) => '<article>'.$html.'</article>');
    Filters::add(ApiEntryFilter::class, fn (array $data) => [...$data, 'reading_time' => 1]);

    $this->getJson('/api/v1/entries/post/night-trains-of-europe')
        ->assertJsonPath('data.html', '<article><p>All aboard.</p></article>')
        ->assertJsonPath('data.reading_time', 1);
});

it('does not show drafts or scheduled entries', function () {
    Entry::factory()->create(['slug' => 'secret-draft']);
    Entry::factory()->scheduled()->create(['slug' => 'coming-soon']);

    $this->getJson('/api/v1/entries/post/secret-draft')->assertNotFound();
    $this->getJson('/api/v1/entries/post/coming-soon')->assertNotFound();
    $this->getJson('/api/v1/entries/post/never-written')->assertNotFound();
});

it('finds nested pages by their full path', function () {
    $about = Entry::factory()->page()->published()->create(['title' => 'About', 'slug' => 'about']);
    $team = Entry::factory()->page()->published()->create(['title' => 'Team', 'slug' => 'team', 'parent_id' => $about->id]);
    $careers = Entry::factory()->page()->published()->create(['title' => 'Careers', 'slug' => 'careers']);
    Entry::factory()->page()->published()->create(['title' => 'Careers Team', 'slug' => 'team', 'parent_id' => $careers->id]);

    $this->getJson('/api/v1/entries/page/about/team')->assertOk()->assertJsonPath('data.id', $team->id)->assertJsonPath('data.path', '/about/team');
    $this->getJson('/api/v1/entries/page/team')->assertNotFound();
    $this->getJson('/api/v1/entries/page/about')->assertJsonPath('data.id', $about->id);
});
