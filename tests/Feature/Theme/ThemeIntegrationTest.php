<?php

use App\Models\User;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);

    $this->author = User::factory()->author()->create(['name' => 'Rui Monteiro']);
    $this->post = Entry::factory()->published()->by($this->author)->create([
        'title' => 'Walking the Levadas of Madeira',
        'slug' => 'walking-the-levadas',
        'excerpt' => 'Three trails along old water channels.',
    ]);
});

it('prints the SEO tags of an entry', function () {
    $this->post->update(['meta' => ['seo' => ['title' => 'Levada walks in Madeira', 'noindex' => true]]]);

    $this->get($this->post->path())
        ->assertOk()
        ->assertSee('<title>Levada walks in Madeira</title>', false)
        ->assertSee('<meta name="robots" content="noindex', false)
        ->assertSee('"@type": "BreadcrumbList"', false);
});

it('prints general SEO tags on list pages', function () {
    app(Settings::class)->set('site.name', 'Atlantic Notes');

    $this->get('/blog')
        ->assertOk()
        ->assertSee('<title>Posts'.config('verso.seo.title_separator').'Atlantic Notes</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/blog').'">', false);
});

it('shows the primary menu in the header when one is set', function () {
    $menu = Menu::factory()->at('primary')->create();
    MenuItem::factory()->for($menu)->create(['label' => 'Contact', 'url' => '/contact']);

    $this->get('/')
        ->assertOk()
        ->assertSee('verso-menu--primary', false)
        ->assertSee('<a href="'.url('/contact').'"', false);
});

it('falls back to top level pages without a primary menu', function () {
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'About the Island', 'slug' => 'about']);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('verso-menu--primary', false)
        ->assertSee('About the Island');
});

it('shows the footer menu when one is set', function () {
    $menu = Menu::factory()->at('footer')->create();
    MenuItem::factory()->for($menu)->create(['label' => 'Newsletter', 'url' => 'https://letters.example.org/signup']);

    $this->get('/')
        ->assertOk()
        ->assertSee('verso-menu--footer', false)
        ->assertSee('Newsletter');
});

it('shows approved comments and the form on posts', function () {
    Comment::factory()->approved()->on($this->post)->create(['author_name' => 'Joana Abreu', 'body' => 'The Caldeirão Verde trail was magical.']);
    Comment::factory()->spam()->on($this->post)->create(['author_name' => 'Cheap Pills', 'body' => 'Buy now']);

    $this->get($this->post->path())
        ->assertOk()
        ->assertSee('id="comments"', false)
        ->assertSee('Joana Abreu')
        ->assertSee('The Caldeirão Verde trail was magical.')
        ->assertDontSee('Cheap Pills')
        ->assertSee(route('comments.store', $this->post), false);
});

it('hides comments when they are turned off for the site', function () {
    app(Settings::class)->set('comments.enabled', false);

    $this->get($this->post->path())
        ->assertOk()
        ->assertDontSee('id="comments"', false);
});

it('renders a page with the full width template of the default theme', function () {
    Entry::factory()->page()->published()->by($this->author)->create([
        'title' => 'Ferry Timetable',
        'slug' => 'ferry-timetable',
        'meta' => ['template' => 'full-width'],
    ]);

    $this->get('/ferry-timetable')
        ->assertOk()
        ->assertSee('class="entry entry--wide"', false)
        ->assertSee('Ferry Timetable');
});

it('renders a page without a template at the normal width', function () {
    Entry::factory()->page()->published()->by($this->author)->create(['title' => 'Harbor Rules', 'slug' => 'harbor-rules']);

    $this->get('/harbor-rules')->assertOk()->assertSee('<article class="entry">', false)->assertDontSee('class="entry entry--wide"', false);
});
