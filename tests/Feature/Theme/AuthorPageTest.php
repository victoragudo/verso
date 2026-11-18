<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Enums\ArchiveKind;
use App\Verso\Theme\Templates\TemplateContext;
use App\Verso\Theme\Templates\TemplateHierarchy;

beforeEach(function () {
    $this->author = User::factory()->author()->create([
        'name' => 'Pablo Ortega',
        'bio' => 'Pablo takes the photos and prefers trains to planes.',
        'social_links' => ['https://bsky.app/profile/pablo.example', 'https://pablo-ortega.example/about'],
    ]);
    $this->post = Entry::factory()->published()->by($this->author)->create(['title' => 'A slow weekend in Porto', 'slug' => 'slow-weekend-porto']);
});

it('lists the published posts of an author with the bio and links on both themes', function (string $theme) {
    app(Settings::class)->set('theme.active', $theme);
    Entry::factory()->by($this->author)->create(['title' => 'Draft about Lisbon trams']);
    Entry::factory()->published()->create(['title' => 'Somebody else wrote this']);

    $this->get('/author/pablo-ortega')
        ->assertOk()
        ->assertSee('<h1 class="page-title">Pablo Ortega</h1>', false)
        ->assertSee('Pablo takes the photos and prefers trains to planes.')
        ->assertSee('A slow weekend in Porto')
        ->assertDontSee('Draft about Lisbon trams')
        ->assertDontSee('Somebody else wrote this')
        ->assertSee('href="https://bsky.app/profile/pablo.example" rel="me noopener"', false)
        ->assertSee('>Bluesky</a>', false)
        ->assertSee('>pablo-ortega.example</a>', false);
})->with(['ashgrove', 'default']);

it('has no page for people without published posts or unknown slugs', function () {
    $reader = User::factory()->create(['name' => 'Quiet Reader']);
    Entry::factory()->by($reader)->create(['title' => 'Unfinished thoughts']);

    $this->get('/author/quiet-reader')->assertNotFound();
    $this->get('/author/nobody-here')->assertNotFound();
});

it('links the author box and the post meta to the author page', function (string $theme) {
    app(Settings::class)->set('theme.active', $theme);

    $this->get($this->post->path())
        ->assertOk()
        ->assertSee('href="'.url('/author/pablo-ortega').'" rel="author"', false)
        ->assertSee('Pablo takes the photos and prefers trains to planes.');
})->with(['ashgrove', 'default']);

it('looks for author views from the most specific one', function () {
    $candidates = app(TemplateHierarchy::class)->forAuthor($this->author);

    expect($candidates->context)->toBe(TemplateContext::Author)
        ->and($candidates->names)->toBe(['author-pablo-ortega', 'author', 'archive'])
        ->and(ArchiveKind::Author->value)->toBe('author');
});

it('uses the configured base for the author URL', function () {
    expect($this->author->authorUrl())->toBe(url('/author/pablo-ortega'))
        ->and(route('theme.author', 'pablo-ortega', false))->toBe('/author/pablo-ortega');
});
