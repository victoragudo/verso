<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false]);
    $this->author = User::factory()->author()->create(['name' => 'Inês Carvalho']);
});

it('never gives the body the entry class on a page', function () {
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'About Harbor Lane', 'slug' => 'about']);

    $html = $this->get('/about')->assertOk()->getContent();

    expect($html)->toContain('<body class="single type-page slug-about">')
        ->and($html)->toContain('<article class="entry">')
        ->and($html)->not->toMatch('/<body[^>]*class="[^"]*\bentry\b/');
});

it('does not let a slug turn into a theme class on the body', function () {
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'Header Ideas', 'slug' => 'header']);

    expect($this->get('/header')->assertOk()->getContent())->not->toContain('entry-header single');
});

it('limits only the article width in the default theme css', function () {
    $css = file_get_contents(base_path('themes/default/assets/theme.css'));

    expect($css)->toMatch('/article\.entry\s*\{\s*max-width:\s*var\(--measure\)/')
        ->and($css)->not->toMatch('/(^|\n)\.entry\s*\{/');
});
