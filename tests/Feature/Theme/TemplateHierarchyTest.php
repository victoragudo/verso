<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\TemplateCandidatesFilter;
use App\Verso\Theme\Templates\TemplateContext;
use Tests\Support\ThemeSandbox;

// A view that extends the layout and prints a marker, so a test can see which template was used.
function markerView(string $marker): string
{
    return "@extends('theme::layouts.app')\n@section('content')<p class=\"marker\">{$marker}</p>@endsection\n";
}

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);

    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', [
        'templates' => [
            'landing' => ['label' => 'Landing page', 'types' => ['page']],
            'wide-story' => ['label' => 'Wide story', 'view' => 'layouts.wide-story', 'types' => ['post', 'page']],
        ],
    ])->activate('harbor');

    $this->author = User::factory()->author()->create(['name' => 'Marta Lindqvist']);
});

afterEach(function () {
    Filters::clear(TemplateCandidatesFilter::class);
    $this->sandbox->destroy();
});

describe('entries', function () {
    beforeEach(function () {
        $this->post = Entry::factory()->published()->by($this->author)->create([
            'title' => 'Ferry Routes of the Stockholm Archipelago',
            'slug' => 'ferry-routes',
        ]);
    });

    it('uses entry-{type}-{slug} first', function () {
        $this->sandbox->files('harbor', [
            'views/entry-post-ferry-routes.blade.php' => markerView('slug view'),
            "views/entry-post-{$this->post->id}.blade.php" => markerView('id view'),
            'views/entry-post.blade.php' => markerView('type view'),
            'views/entry.blade.php' => markerView('entry view'),
        ]);

        $this->get($this->post->path())->assertOk()->assertSee('slug view')->assertDontSee('id view');
    });

    it('uses entry-{type}-{id} when there is no slug view', function () {
        $this->sandbox->files('harbor', [
            "views/entry-post-{$this->post->id}.blade.php" => markerView('id view'),
            'views/entry-post.blade.php' => markerView('type view'),
        ]);

        $this->get($this->post->path())->assertOk()->assertSee('id view')->assertDontSee('type view');
    });

    it('uses entry-{type} before entry', function () {
        $this->sandbox->files('harbor', [
            'views/entry-post.blade.php' => markerView('type view'),
            'views/entry.blade.php' => markerView('entry view'),
        ]);

        $this->get($this->post->path())->assertOk()->assertSee('type view')->assertDontSee('entry view');
    });

    it('falls back to the entry view of the default theme', function () {
        $this->get($this->post->path())
            ->assertOk()
            ->assertSee('Ferry Routes of the Stockholm Archipelago')
            ->assertSee('class="entry"', false);
    });

    it('does not use the view of another slug', function () {
        $this->sandbox->files('harbor', ['views/entry-post-island-hopping.blade.php' => markerView('other slug view')]);

        $this->get($this->post->path())->assertOk()->assertDontSee('other slug view');
    });

    it('uses the page template saved on the entry first', function () {
        $this->sandbox->files('harbor', [
            'views/layouts/wide-story.blade.php' => markerView('wide story template'),
            'views/entry-post-ferry-routes.blade.php' => markerView('slug view'),
        ]);
        $this->post->update(['meta' => ['template' => 'wide-story']]);

        $this->get($this->post->path())->assertOk()->assertSee('wide story template')->assertDontSee('slug view');
    });

    it('ignores a saved template that the theme does not offer for the type', function () {
        $this->sandbox->files('harbor', [
            'views/templates/landing.blade.php' => markerView('landing template'),
            'views/entry-post.blade.php' => markerView('type view'),
        ]);
        $this->post->update(['meta' => ['template' => 'landing']]);

        $this->get($this->post->path())->assertOk()->assertSee('type view')->assertDontSee('landing template');
    });

    it('skips a declared template whose view file is missing', function () {
        $this->sandbox->files('harbor', ['views/entry-post.blade.php' => markerView('type view')]);
        $this->post->update(['meta' => ['template' => 'wide-story']]);

        $this->get($this->post->path())->assertOk()->assertSee('type view');
    });
});

describe('pages and the front page', function () {
    beforeEach(function () {
        $this->page = Entry::factory()->page()->published()->by($this->author)->create(['title' => 'Visit the Harbor', 'slug' => 'visit']);
    });

    it('uses the templates folder for page templates by default', function () {
        $this->sandbox->files('harbor', ['views/templates/landing.blade.php' => markerView('landing template')]);
        $this->page->update(['meta' => ['template' => 'landing']]);

        $this->get('/visit')->assertOk()->assertSee('landing template');
    });

    it('keeps entry-page of the default theme for pages without a template', function () {
        $this->get('/visit')->assertOk()->assertSee('Visit the Harbor');
    });

    it('uses front-page for the chosen front page', function () {
        $this->sandbox->files('harbor', [
            'views/front-page.blade.php' => markerView('front page view'),
            'views/entry-page-visit.blade.php' => markerView('slug view'),
        ]);
        app(Settings::class)->set('reading.front_page', $this->page->id);

        $this->get('/')->assertOk()->assertSee('front page view')->assertDontSee('slug view');
    });

    it('lets a page template win over front-page', function () {
        $this->sandbox->files('harbor', [
            'views/front-page.blade.php' => markerView('front page view'),
            'views/templates/landing.blade.php' => markerView('landing template'),
        ]);
        $this->page->update(['meta' => ['template' => 'landing']]);
        app(Settings::class)->set('reading.front_page', $this->page->id);

        $this->get('/')->assertOk()->assertSee('landing template');
    });

    it('falls back to the entry views for the front page without front-page', function () {
        $this->sandbox->files('harbor', ['views/entry-page-visit.blade.php' => markerView('slug view')]);
        app(Settings::class)->set('reading.front_page', $this->page->id);

        $this->get('/')->assertOk()->assertSee('slug view');
    });

    it('uses home for the latest posts', function () {
        $this->sandbox->files('harbor', ['views/home.blade.php' => markerView('harbor home')]);

        $this->get('/')->assertOk()->assertSee('harbor home');
    });
});

describe('taxonomies and archives', function () {
    beforeEach(function () {
        $this->category = Term::factory()->create(['name' => 'Sailing', 'slug' => 'sailing']);
        Entry::factory()->published()->by($this->author)->create(['title' => 'Reefing in a Squall'])->terms()->attach($this->category);
    });

    it('uses taxonomy-{taxonomy}-{slug} first', function () {
        $this->sandbox->files('harbor', [
            'views/taxonomy-category-sailing.blade.php' => markerView('sailing view'),
            'views/taxonomy-category.blade.php' => markerView('category view'),
        ]);

        $this->get($this->category->path())->assertOk()->assertSee('sailing view');
    });

    it('uses taxonomy-{taxonomy} before taxonomy', function () {
        $this->sandbox->files('harbor', [
            'views/taxonomy-category.blade.php' => markerView('category view'),
            'views/taxonomy.blade.php' => markerView('taxonomy view'),
        ]);

        $this->get($this->category->path())->assertOk()->assertSee('category view')->assertDontSee('taxonomy view');
    });

    it('gives the taxonomy and the term to the view', function () {
        $this->sandbox->files('harbor', [
            'views/taxonomy.blade.php' => "@extends('theme::layouts.app')\n@section('content')<p>{{ \$taxonomy->label }}: {{ \$term->name }} ({{ \$entries->total() }})</p>@endsection\n",
        ]);

        $this->get($this->category->path())->assertOk()->assertSee('Category: Sailing (1)');
    });

    it('uses archive-{type} before archive', function () {
        $this->sandbox->files('harbor', [
            'views/archive-post.blade.php' => markerView('post archive view'),
            'views/archive.blade.php' => markerView('archive view'),
        ]);

        $this->get('/blog')->assertOk()->assertSee('post archive view')->assertDontSee('archive view<');
    });

    it('falls back to the archive view of the default theme', function () {
        $this->get('/blog')->assertOk()->assertSee('Reefing in a Squall');
    });
});

describe('search and not found', function () {
    it('uses the search view of the active theme', function () {
        $this->sandbox->files('harbor', ['views/search.blade.php' => markerView('harbor search')]);

        $this->get('/search?q=ferry')->assertOk()->assertSee('harbor search');
    });

    it('uses the 404 view of the active theme', function () {
        $this->sandbox->files('harbor', ['views/404.blade.php' => markerView('harbor lost at sea')]);

        $this->get('/no-such-harbor')->assertNotFound()->assertSee('harbor lost at sea');
    });
});

describe('the candidates filter', function () {
    beforeEach(function () {
        $this->post = Entry::factory()->published()->by($this->author)->create(['title' => 'Lighthouses of Gotland', 'slug' => 'lighthouses', 'meta' => ['featured' => true]]);
        $this->sandbox->files('harbor', [
            'views/entry-featured.blade.php' => markerView('featured view'),
            'views/entry-post.blade.php' => markerView('type view'),
        ]);
    });

    it('lets plugins add a candidate', function () {
        Filters::add(TemplateCandidatesFilter::class, function (array $candidates, TemplateContext $context, ?object $subject): array {
            if ($context === TemplateContext::Entry && $subject instanceof Entry && ($subject->meta['featured'] ?? false)) {
                array_unshift($candidates, 'entry-featured');
            }

            return $candidates;
        });

        $this->get($this->post->path())->assertOk()->assertSee('featured view');
    });

    it('passes the context and subject to the callbacks', function () {
        $seen = [];
        Filters::add(TemplateCandidatesFilter::class, function (array $candidates, TemplateContext $context, ?object $subject) use (&$seen): array {
            $seen[] = [$context, $subject === null ? null : $subject::class, $candidates];

            return $candidates;
        });

        $this->get($this->post->path())->assertOk();
        $this->get('/search?q=gotland')->assertOk();

        expect($seen[0])->toBe([TemplateContext::Entry, Entry::class, ['entry-post-lighthouses', "entry-post-{$this->post->id}", 'entry-post', 'entry']])
            ->and($seen[1])->toBe([TemplateContext::Search, null, ['search']]);
    });

    it('drops unsafe names that a plugin returns', function () {
        Filters::add(TemplateCandidatesFilter::class, fn (array $candidates): array => ['../../.env', '/etc/passwd', 'entry..post', 42, ...$candidates]);

        $this->get($this->post->path())->assertOk()->assertSee('type view');
    });

    it('shows the 404 page error when a plugin leaves no usable candidate', function () {
        Filters::add(TemplateCandidatesFilter::class, fn (array $candidates, TemplateContext $context): array => $context === TemplateContext::Entry ? ['entry-nowhere'] : $candidates);

        $this->withoutExceptionHandling();

        expect(fn () => $this->get($this->post->path()))->toThrow(InvalidArgumentException::class, 'The theme has no [entry-nowhere] view.');
    });
});
