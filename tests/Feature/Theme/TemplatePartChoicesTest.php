<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Templates\PageTemplate;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Templates\TemplatePartChoices;
use App\Verso\Theme\ThemeManager;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $this->admin = User::factory()->admin()->create(['name' => 'Ingrid Dahl']);
});

function landingTestPage(array $meta = []): Entry
{
    return Entry::factory()->page()->published()->create(['title' => 'Spring workshop', 'slug' => 'spring-workshop-'.uniqid(), 'meta' => $meta]);
}

it('reads the part styles of a page template from theme.json', function () {
    $template = PageTemplate::fromManifest('landing', ['label' => 'Landing page', 'parts' => ['header' => 'minimal', 'footer' => 'Not Valid', 'Bad Part' => 'x', 'sidebar' => 3]]);

    expect($template->parts)->toBe(['header' => 'minimal'])
        ->and(PageTemplate::fromManifest('plain', [])->parts)->toBe([]);
});

it('shows the minimal header on pages that use the landing template', function () {
    $landing = landingTestPage(['template' => 'landing']);
    $plain = landingTestPage();

    $this->get($landing->path())->assertOk()
        ->assertSee('class="site-header is-minimal"', false)
        ->assertDontSee('id="site-menu"', false);

    $this->get($plain->path())->assertOk()
        ->assertSee('data-site-header', false)
        ->assertDontSee('is-minimal', false);
});

it('lets one entry pick its own header style over the template', function () {
    $centered = landingTestPage(['template' => 'landing', 'parts' => ['header' => 'centered']]);
    $minimal = landingTestPage(['parts' => ['header' => 'minimal']]);

    $this->get($centered->path())->assertOk()->assertSee('site-header is-centered', false);
    $this->get($minimal->path())->assertOk()->assertSee('class="site-header is-minimal"', false);
});

it('ignores styles the theme does not offer', function () {
    $entry = landingTestPage(['parts' => ['header' => 'floating', 'footer' => ['four-columns']]]);

    expect(app(TemplatePartChoices::class)->forEntry($entry))->toBe([]);

    $this->get($entry->path())->assertOk()->assertSee('data-site-header', false);
});

it('keeps the site wide style on other pages and the home page', function () {
    app(PartVariants::class)->choose(app(ThemeManager::class)->find('ashgrove'), 'header', 'centered');
    $landing = landingTestPage(['template' => 'landing']);

    $this->get($landing->path())->assertOk()->assertSee('is-minimal', false);
    $this->get('/')->assertOk()->assertSee('site-header is-centered', false)->assertDontSee('is-minimal', false);
});

it('uses the template choice saved in the site editor over theme.json', function () {
    $theme = app(ThemeManager::class)->find('ashgrove');
    $choices = app(TemplatePartChoices::class);
    $choices->saveForTemplate($theme, 'landing', ['header' => 'centered', 'footer' => 'four-columns', 'sidebar' => 'left']);
    $landing = landingTestPage(['template' => 'landing']);

    expect($choices->saved($theme))->toBe(['landing' => ['header' => 'centered', 'footer' => 'four-columns']])
        ->and($choices->forEntry($landing))->toBe(['header' => 'centered', 'footer' => 'four-columns']);

    $this->get($landing->path())->assertOk()->assertSee('site-header is-centered', false);

    $choices->saveForTemplate($theme, 'landing', []);

    expect($choices->saved($theme))->toBe([])
        ->and(app(Settings::class)->has(TemplatePartChoices::SETTING.'ashgrove'))->toBeFalse();
});

it('offers a minimal header on the default theme too', function () {
    app(Settings::class)->set('theme.active', 'default');
    $landing = landingTestPage(['template' => 'landing']);

    expect(array_column(app(TemplatePartChoices::class)->options(), 'name'))->toBe(['header']);

    $this->get($landing->path())->assertOk()
        ->assertSee('class="site-header is-minimal"', false)
        ->assertDontSee('aria-label="Main"', false);
});

describe('entry editor', function () {
    it('shares the part choices of the active theme', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.entries.create', 'page'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('partChoices.0.name', 'header')
                ->where('partChoices.0.default', 'classic')
                ->where('partChoices.0.options.2.value', 'minimal')
                ->where('partChoices.1.name', 'footer'));
    });

    it('saves a style the theme offers and refuses others', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.entries.store', 'page'), ['title' => 'Harvest fair', 'status' => 'draft', 'meta' => ['parts' => ['header' => 'minimal']]])
            ->assertSessionHasNoErrors();

        expect(Entry::query()->where('title', 'Harvest fair')->first()->meta['parts'])->toBe(['header' => 'minimal']);

        $this->actingAs($this->admin)
            ->post(route('admin.entries.store', 'page'), ['title' => 'Winter fair', 'status' => 'draft', 'meta' => ['parts' => ['header' => 'floating']]])
            ->assertSessionHasErrors('meta.parts.header');

        $this->actingAs($this->admin)
            ->post(route('admin.entries.store', 'page'), ['title' => 'Autumn fair', 'status' => 'draft', 'meta' => ['parts' => ['header' => ['minimal']]]])
            ->assertSessionHasErrors('meta.parts.header');
    });

    it('keeps a stored style that the theme no longer offers', function () {
        $entry = Entry::factory()->page()->create(['title' => 'Old fair', 'meta' => ['parts' => ['header' => 'retired']]]);

        $this->actingAs($this->admin)
            ->put(route('admin.entries.update', ['page', $entry]), ['title' => 'Old fair', 'status' => 'draft', 'meta' => ['parts' => ['header' => 'retired']]])
            ->assertSessionHasNoErrors();
    });
});

describe('site editor templates', function () {
    it('lists the page templates with their header and footer styles', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.site-editor.templates'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('templateParts.parts.0.name', 'header')
                ->where('templateParts.templates.1.name', 'landing')
                ->where('templateParts.templates.1.themeParts.header', 'minimal')
                ->where('templateParts.templates.1.styles.header', 'minimal'));
    });

    it('saves and clears the styles of one template', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.site-editor.templates.parts', 'landing'), ['parts' => ['header' => 'centered', 'footer' => null]])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        expect(app(TemplatePartChoices::class)->saved(app(ThemeManager::class)->find('ashgrove')))->toBe(['landing' => ['header' => 'centered']]);

        $this->actingAs($this->admin)
            ->put(route('admin.site-editor.templates.parts', 'landing'), ['parts' => ['header' => null]])
            ->assertSessionHasNoErrors();

        expect(app(TemplatePartChoices::class)->saved(app(ThemeManager::class)->find('ashgrove')))->toBe([]);
    });

    it('refuses unknown templates, styles and people without theme rights', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.site-editor.templates.parts', 'gallery'), ['parts' => []])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put(route('admin.site-editor.templates.parts', 'landing'), ['parts' => ['header' => 'floating']])
            ->assertSessionHasErrors('parts.header');

        $this->actingAs($this->admin)
            ->put(route('admin.site-editor.templates.parts', 'landing'), ['parts' => ['sidebar' => 'left']])
            ->assertSessionHasErrors('parts.sidebar');

        $this->actingAs(User::factory()->editor()->create())
            ->put(route('admin.site-editor.templates.parts', 'landing'), ['parts' => ['header' => 'centered']])
            ->assertForbidden();
    });
});
