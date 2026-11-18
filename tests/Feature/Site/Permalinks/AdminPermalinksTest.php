<?php

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Permalinks\PermalinkSettings;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $this->admin = User::factory()->admin()->create(['name' => 'Rita Sousa']);
});

function permalinkForm(array $overrides = []): array
{
    return array_replace_recursive([
        'structures' => ['post' => '/blog/{slug}', 'page' => '/{slug}'],
        'archive_bases' => ['post' => 'blog'],
        'taxonomy_bases' => ['category' => 'category', 'tag' => 'tag'],
        'trailing_slash' => false,
        'lowercase' => false,
    ], $overrides);
}

it('shows the page with structures, presets and live preview samples', function () {
    $post = Entry::factory()->published()->create(['title' => 'Sardine Season in Lisbon', 'slug' => 'sardine-season', 'published_at' => '2026-06-13 08:00:00']);
    $post->terms()->attach(Term::factory()->create(['taxonomy' => 'category', 'name' => 'Food', 'slug' => 'food']));

    $this->actingAs($this->admin)
        ->get(route('admin.site.permalinks.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site/permalinks')
            ->where('permalinks.structures.post', '/blog/{slug}')
            ->where('permalinks.structures.page', '/{slug}')
            ->where('permalinks.archive_bases.post', 'blog')
            ->where('permalinks.taxonomy_bases.category', 'category')
            ->where('presets.month', '/{year}/{month}/{slug}')
            ->where('tokens', ['slug', 'id', 'year', 'month', 'day', 'category'])
            ->where('types.0.name', 'post')
            ->where('types.0.sample.slug', 'sardine-season')
            ->where('types.0.sample.year', '2026')
            ->where('types.0.sample.month', '06')
            ->where('types.0.sample.category', 'food')
            ->where('types.1.sample.slug', 'about/team')
            ->where('sitemap.enabled', true)
            ->where('sitemap.types', ['post', 'page'])
            ->where('sitemapStatus.post', 1)
            ->where('search.discourage', false)
            ->where('search.title_format', '{title}{sep}{site}')
            ->where('links.sitemap', url('/sitemap.xml'))
            ->has('entryOptions', 1)
        );
});

it('is only for users who manage settings', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.site.permalinks.edit'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.site.permalinks.update'), permalinkForm())->assertForbidden();
    $this->actingAs($editor)->put(route('admin.site.permalinks.sitemap'), [])->assertForbidden();
    $this->actingAs($editor)->put(route('admin.site.permalinks.search'), [])->assertForbidden();
});

it('saves a new structure and lowercases it', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm(['structures' => ['post' => ' /Articles/{year}/{slug}/ '], 'trailing_slash' => true, 'lowercase' => true]))
        ->assertRedirect(route('admin.site.permalinks.edit', ['tab' => 'permalinks']))
        ->assertSessionHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->get(PermalinkSettings::STRUCTURES))->toBe(['post' => '/articles/{year}/{slug}', 'page' => '/{slug}'])
        ->and($settings->get(PermalinkSettings::TRAILING_SLASH))->toBeTrue()
        ->and($settings->get(PermalinkSettings::LOWERCASE))->toBeTrue();
});

it('refuses broken structures', function (string $structure, string $message) {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm(['structures' => ['post' => $structure]]))
        ->assertSessionHasErrors(['structures.post' => $message]);
})->with([
    'unknown token' => ['/blog/{author}/{slug}', 'Unknown token {author}. Use {slug}, {id}, {year}, {month}, {day}, {category}.'],
    'no slug or id' => ['/{year}/{month}', 'The structure needs {slug} or {id}.'],
    'token twice' => ['/{slug}/{slug}', 'Use each token only once.'],
    'tokens glued' => ['/{year}{month}/{slug}', 'Put a slash between two tokens.'],
    'odd characters' => ['/blog/{slug}?ref=1', 'Use lowercase letters, numbers, dashes, dots, slashes and tokens only.'],
    'reserved word' => ['/admin/{slug}', '/admin is used by Verso or a plugin. Pick another word.'],
    'core route' => ['/search/{slug}', '/search is used by Verso or a plugin. Pick another word.'],
    'taken route' => ['/sitemap.xml/{slug}', '/sitemap.xml is used by Verso or a plugin. Pick another word.'],
]);

it('needs {slug} for hierarchical types', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm(['structures' => ['page' => '/pages/{id}']]))
        ->assertSessionHasErrors(['structures.page' => 'Nested entries need the {slug} token.']);
});

it('refuses two flat types with the same structure', function () {
    app(ContentTypeRegistry::class)->register(new ContentType(name: 'recipe', label: 'Recipe', pluralLabel: 'Recipes', routeBase: 'recipes', menuOrder: 30));

    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm(['structures' => ['recipe' => '/blog/{slug}']]))
        ->assertSessionHasErrors(['structures.recipe' => 'This structure is the same as the one of Posts.']);
});

it('allows posts and pages to share the plain structure', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm(['structures' => ['post' => '/{slug}']]))
        ->assertSessionHasNoErrors();
});

it('refuses clashing bases', function (array $overrides, string $field, string $message) {
    Entry::factory()->page()->published()->create(['title' => 'Our Journal', 'slug' => 'journal']);

    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm($overrides))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'same as taxonomy' => [['archive_bases' => ['post' => 'tag']], 'taxonomy_bases.tag', 'Another archive or taxonomy already uses /tag.'],
    'two taxonomies' => [['taxonomy_bases' => ['tag' => 'category']], 'taxonomy_bases.tag', 'Another archive or taxonomy already uses /category.'],
    'empty taxonomy' => [['taxonomy_bases' => ['tag' => '']], 'taxonomy_bases.tag', 'Enter a base, like "topics".'],
    'bad characters' => [['taxonomy_bases' => ['tag' => 'tags & labels']], 'taxonomy_bases.tag', 'Use lowercase letters, numbers, dashes and slashes only.'],
    'reserved' => [['archive_bases' => ['post' => 'feed']], 'archive_bases.post', '/feed is used by Verso or a plugin. Pick another word.'],
    'existing page' => [['archive_bases' => ['post' => 'journal']], 'archive_bases.post', 'The page "Our Journal" already uses /journal.'],
]);

it('turns the archive off with an empty base', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.update'), permalinkForm(['archive_bases' => ['post' => '']]))
        ->assertSessionHasNoErrors();

    config(['verso.page_cache.enabled' => false]);
    $this->get('/blog')->assertNotFound();
});
