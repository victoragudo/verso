<?php

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Permalinks\Permalinks;
use App\Verso\Site\Permalinks\PermalinkSettings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false]);

    $this->author = User::factory()->author()->create(['name' => 'Marta Oliveira']);
    $this->post = Entry::factory()->published()->by($this->author)->create([
        'title' => 'Riding Tram 28 Through Alfama',
        'slug' => 'riding-tram-28',
        'published_at' => '2026-03-09 10:30:00',
    ]);
});

function usePostStructure(string $pattern): void
{
    app(Settings::class)->set(PermalinkSettings::STRUCTURES, ['post' => $pattern]);
}

it('keeps the default /blog/{slug} structure', function () {
    expect($this->post->path())->toBe('/blog/riding-tram-28');

    $this->get('/blog/riding-tram-28')->assertOk()->assertSee('Riding Tram 28 Through Alfama');
    $this->get('/blog')->assertOk()->assertSee('Riding Tram 28 Through Alfama');
});

it('builds and serves every preset structure both ways', function (string $pattern, string $path) {
    usePostStructure($pattern);
    $travel = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Travel', 'slug' => 'travel']);
    $this->post->terms()->attach($travel);
    $post = $this->post->fresh();

    expect($post->path())->toBe(str_replace('{id}', (string) $post->id, $path));

    $this->get($post->path())->assertOk()->assertSee('Riding Tram 28 Through Alfama');
})->with([
    'plain' => ['/{slug}', '/riding-tram-28'],
    'month' => ['/{year}/{month}/{slug}', '/2026/03/riding-tram-28'],
    'day' => ['/{year}/{month}/{day}/{slug}', '/2026/03/09/riding-tram-28'],
    'category' => ['/{category}/{slug}', '/travel/riding-tram-28'],
    'numeric' => ['/archives/{id}', '/archives/{id}'],
    'custom' => ['/articles/{year}/{slug}', '/articles/2026/riding-tram-28'],
]);

it('uses the site time zone for date tokens', function () {
    app(Settings::class)->set('site.timezone', 'Pacific/Auckland');
    usePostStructure('/{year}/{month}/{day}/{slug}');
    $this->post->forceFill(['published_at' => '2026-12-31 15:00:00'])->save();

    expect($this->post->fresh()->path())->toBe('/2027/01/01/riding-tram-28');
});

it('uses the uncategorized slug when an entry has no category', function () {
    usePostStructure('/{category}/{slug}');

    expect($this->post->path())->toBe('/uncategorized/riding-tram-28');
    $this->get('/uncategorized/riding-tram-28')->assertOk();
});

it('redirects a wrong date or category to the right path', function () {
    usePostStructure('/{year}/{month}/{slug}');

    $this->get('/2025/01/riding-tram-28?ref=newsletter')->assertRedirect(url('/2026/03/riding-tram-28?ref=newsletter'))->assertStatus(301);
});

it('returns 404 for paths that fit no structure', function () {
    usePostStructure('/{year}/{month}/{slug}');

    $this->get('/2026/03/no-such-story')->assertNotFound();
    $this->get('/blog/riding-tram-28/extra')->assertNotFound();
});

it('serves posts and pages that share the plain structure', function () {
    usePostStructure('/{slug}');
    $about = Entry::factory()->page()->published()->by($this->author)->create(['title' => 'About Our Kitchen', 'slug' => 'about']);
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'Meet the Team', 'slug' => 'team', 'parent_id' => $about->id]);

    $this->get('/riding-tram-28')->assertOk()->assertSee('Riding Tram 28 Through Alfama');
    $this->get('/about')->assertOk()->assertSee('About Our Kitchen');
    $this->get('/about/team')->assertOk()->assertSee('Meet the Team');
});

it('moves the archive and taxonomy bases', function () {
    app(Settings::class)->setMany([
        PermalinkSettings::ARCHIVE_BASES => ['post' => 'journal'],
        PermalinkSettings::TAXONOMY_BASES => ['category' => 'topics', 'tag' => 'labels'],
    ]);
    $food = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Food', 'slug' => 'food']);
    $pastry = Term::factory()->tag()->create(['name' => 'Pastry', 'slug' => 'pastry']);
    $this->post->terms()->attach([$food->id, $pastry->id]);

    expect($food->path())->toBe('/topics/food')->and($pastry->path())->toBe('/labels/pastry');

    $this->get('/journal')->assertOk()->assertSee('Riding Tram 28 Through Alfama')->assertSee('class="archive type-post"', false);
    $this->get('/topics/food')->assertOk()->assertSee('Riding Tram 28 Through Alfama')->assertSee('class="taxonomy taxonomy-category"', false);
    $this->get('/labels/pastry')->assertOk();
    $this->get('/category/food')->assertNotFound();
});

it('adds a trailing slash to generated paths when it is on', function () {
    app(Settings::class)->set(PermalinkSettings::TRAILING_SLASH, true);
    $travel = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Travel', 'slug' => 'travel']);

    expect($this->post->path())->toBe('/blog/riding-tram-28/')
        ->and($travel->path())->toBe('/category/travel/')
        ->and(app(Permalinks::class)->archivePath('post'))->toBe('/blog/')
        ->and(app(Permalinks::class)->finish('/sitemap.xml'))->toBe('/sitemap.xml');

    $this->get('/blog/riding-tram-28/')->assertOk();
    $this->get('/blog/riding-tram-28')->assertOk()->assertSee('<link rel="canonical" href="'.url('/blog/riding-tram-28/').'">', false);
});

it('sends mixed case paths to the lowercase path when enforced', function () {
    app(Settings::class)->set(PermalinkSettings::LOWERCASE, true);

    $this->get('/Blog/Riding-Tram-28')->assertStatus(301)->assertRedirect(url('/blog/riding-tram-28'));
});

it('leaves mixed case paths alone when not enforced', function () {
    $this->get('/Blog/Riding-Tram-28')->assertNotFound();
});

it('lets a plugin type use its own structure', function () {
    app(ContentTypeRegistry::class)->register(new ContentType(name: 'event', label: 'Event', pluralLabel: 'Events', hasArchive: true, routeBase: 'events'));
    app(Settings::class)->set(PermalinkSettings::STRUCTURES, ['event' => '/whats-on/{year}/{slug}']);
    $event = Entry::factory()->published()->by($this->author)->create(['type' => 'event', 'title' => 'Fado Night at the Old Market', 'slug' => 'fado-night', 'published_at' => '2026-06-12 20:00:00']);

    expect($event->path())->toBe('/whats-on/2026/fado-night');
    $this->get('/whats-on/2026/fado-night')->assertOk()->assertSee('Fado Night at the Old Market');
});

it('sends the front page to the root from any structure', function () {
    usePostStructure('/{slug}');
    $home = Entry::factory()->page()->published()->by($this->author)->create(['title' => 'Welcome to Harbor Lane', 'slug' => 'welcome']);
    app(Settings::class)->set('reading.front_page', $home->id);

    expect($home->fresh()->path())->toBe('/');
    $this->get('/welcome')->assertStatus(301)->assertRedirect(url('/'));
});
