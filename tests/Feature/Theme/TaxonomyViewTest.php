<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Theme\Support\ThemeResponse;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    $this->views = storage_path('framework/testing/taxonomy-views-'.uniqid());
    File::ensureDirectoryExists($this->views);
});

afterEach(fn () => File::deleteDirectory($this->views));

it('uses the generic taxonomy view when no specific one exists', function () {
    expect(app(ThemeResponse::class)->taxonomyView('tag'))->toBe('taxonomy');
});

it('prefers a taxonomy-<name> view from a theme or plugin', function () {
    file_put_contents($this->views.'/taxonomy-tag.blade.php', '<h1>Tagged: {{ $term->name }} ({{ $entries->total() }})</h1>');
    app(ThemeManager::class)->addPluginViews($this->views);

    $tag = Term::factory()->create(['taxonomy' => 'tag', 'name' => 'Sourdough', 'slug' => 'sourdough']);
    $entry = Entry::factory()->published()->by(User::factory()->author()->create())->create(['title' => 'A Starter in Five Days']);
    $entry->terms()->attach($tag);

    expect(app(ThemeResponse::class)->taxonomyView('tag'))->toBe('taxonomy-tag')
        ->and(app(ThemeResponse::class)->taxonomyView('category'))->toBe('taxonomy');

    $this->get('/tag/sourdough')->assertOk()->assertSee('Tagged: Sourdough (1)');
});
