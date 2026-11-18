<?php

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Templates\PageTemplate;
use App\Verso\Theme\Templates\PageTemplates;
use App\Verso\Theme\Templates\TemplateCandidates;
use App\Verso\Theme\Templates\TemplateContext;
use App\Verso\Theme\Templates\TemplateHierarchy;
use Illuminate\Support\Facades\Validator;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', [
        'templates' => [
            'landing' => ['label' => 'Landing page', 'description' => 'No sidebar, big hero.'],
            'wide-story' => ['label' => 'Wide story', 'view' => 'layouts/wide-story', 'types' => ['post', 'page']],
            'Bad Name' => ['label' => 'Broken'],
            'escape' => ['view' => '../../secrets'],
            'no-types' => ['types' => []],
            'not-an-object' => 'full-width',
        ],
    ])->activate('harbor');
});

afterEach(function () {
    $this->sandbox->destroy();
});

describe('TemplateCandidates', function () {
    it('keeps safe unique names in order', function () {
        $candidates = new TemplateCandidates(TemplateContext::Entry, [
            'entry-post-harbor-life', 'entry-post', 'entry-post', 'templates/landing', 'shop.cart',
            '../.env', '/etc/passwd', 'entry..post', 'entry post', '', 7, null,
        ]);

        expect($candidates->names)->toBe(['entry-post-harbor-life', 'entry-post', 'templates/landing', 'shop.cart'])
            ->and($candidates->context)->toBe(TemplateContext::Entry);
    });

    it('builds a list with one view', function () {
        expect(TemplateCandidates::single(TemplateContext::Custom, 'shop.checkout')->names)->toBe(['shop.checkout']);
    });
});

describe('PageTemplate', function () {
    it('reads a template with defaults', function () {
        $template = PageTemplate::fromManifest('full-width', []);

        expect($template->label)->toBe('Full Width')
            ->and($template->view)->toBe('templates.full-width')
            ->and($template->types)->toBe(['page'])
            ->and($template->appliesTo('page'))->toBeTrue()
            ->and($template->appliesTo('post'))->toBeFalse()
            ->and($template->toOption())->toBe(['value' => 'full-width', 'label' => 'Full Width', 'description' => '']);
    });

    it('refuses broken templates', function (string $name, mixed $config) {
        expect(PageTemplate::fromManifest($name, $config))->toBeNull();
    })->with([
        'bad name' => ['Full Width', []],
        'not an object' => ['full-width', 'templates/full-width'],
        'unsafe view' => ['full-width', ['view' => '../../config/app']],
        'types not a list' => ['full-width', ['types' => 'page']],
        'empty types' => ['full-width', ['types' => ['', 3]]],
    ]);
});

describe('PageTemplates', function () {
    it('lists valid templates of the active theme', function () {
        $names = array_map(fn (PageTemplate $template) => $template->name, app(PageTemplates::class)->all());

        expect($names)->toBe(['landing', 'wide-story']);
    });

    it('lists templates for one content type', function () {
        $templates = app(PageTemplates::class);

        expect(array_column($templates->options('page'), 'value'))->toBe(['landing', 'wide-story'])
            ->and($templates->options('post'))->toBe([['value' => 'wide-story', 'label' => 'Wide story', 'description' => '']])
            ->and($templates->options('product'))->toBe([]);
    });

    it('finds the template saved on an entry', function () {
        $page = Entry::factory()->page()->make(['meta' => ['template' => 'landing']]);
        $post = Entry::factory()->make(['meta' => ['template' => 'landing']]);
        $plain = Entry::factory()->page()->make(['meta' => []]);

        expect(app(PageTemplates::class)->forEntry($page)?->label)->toBe('Landing page')
            ->and(app(PageTemplates::class)->forEntry($post))->toBeNull()
            ->and(app(PageTemplates::class)->forEntry($plain))->toBeNull();
    });

    it('inherits templates from a parent theme, a child template replacing the whole parent one', function () {
        $this->sandbox->theme('harbor-dusk', [
            'parent' => 'harbor',
            'templates' => ['landing' => ['label' => 'Dusk landing'], 'gallery' => ['label' => 'Gallery']],
        ])->activate('harbor-dusk');

        expect(app(PageTemplates::class)->options('page'))->toBe([
            ['value' => 'landing', 'label' => 'Dusk landing', 'description' => ''],
            ['value' => 'wide-story', 'label' => 'Wide story', 'description' => ''],
            ['value' => 'gallery', 'label' => 'Gallery', 'description' => ''],
        ]);
    });

    it('validates the template of an entry', function () {
        $validate = fn (mixed $value, ?Entry $entry = null) => Validator::make(['meta' => ['template' => $value]], app(PageTemplates::class)->rules('page', $entry))->passes();

        $stored = Entry::factory()->page()->create(['meta' => ['template' => 'retired-layout']]);

        expect($validate('landing'))->toBeTrue()
            ->and($validate(null))->toBeTrue()
            ->and($validate('gallery'))->toBeFalse()
            ->and($validate(['landing']))->toBeFalse()
            ->and($validate('retired-layout'))->toBeFalse()
            ->and($validate('retired-layout', $stored))->toBeTrue();
    });
});

describe('TemplateHierarchy', function () {
    it('lists entry candidates from the most specific', function () {
        $entry = Entry::factory()->create(['slug' => 'harbor-life']);

        expect(app(TemplateHierarchy::class)->forEntry($entry)->names)->toBe([
            'entry-post-harbor-life', "entry-post-{$entry->id}", 'entry-post', 'entry',
        ])->and(app(TemplateHierarchy::class)->forEntry($entry)->context)->toBe(TemplateContext::Entry);
    });

    it('puts the page template first and front-page next for the front page', function () {
        $page = Entry::factory()->page()->published()->create(['slug' => 'welcome', 'meta' => ['template' => 'landing']]);
        app(Settings::class)->set('reading.front_page', $page->id);

        $candidates = app(TemplateHierarchy::class)->forEntry($page);

        expect($candidates->context)->toBe(TemplateContext::FrontPage)
            ->and($candidates->names)->toBe(['templates.landing', 'front-page', 'entry-page-welcome', "entry-page-{$page->id}", 'entry-page', 'entry']);
    });

    it('lists taxonomy candidates', function () {
        $term = Term::factory()->tag()->create(['slug' => 'lighthouses']);

        expect(app(TemplateHierarchy::class)->forTerm(app(TaxonomyRegistry::class)->get('tag'), $term)->names)
            ->toBe(['taxonomy-tag-lighthouses', 'taxonomy-tag', 'taxonomy', 'archive']);
    });

    it('lists archive, home, search and 404 candidates', function () {
        $hierarchy = app(TemplateHierarchy::class);

        expect($hierarchy->forArchive(app(ContentTypeRegistry::class)->get('post'))->names)->toBe(['archive-post', 'archive'])
            ->and($hierarchy->home()->names)->toBe(['home'])
            ->and($hierarchy->search()->names)->toBe(['search'])
            ->and($hierarchy->notFound()->names)->toBe(['404'])
            ->and($hierarchy->notFound()->context)->toBe(TemplateContext::NotFound);
    });
});
