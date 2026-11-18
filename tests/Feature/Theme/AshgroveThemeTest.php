<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Customizer\ThemeSettingsSchema;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PluginFixtures;

// Parses a page of the Ashgrove theme so the checks can walk its tags.
function ashgrovePage(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

// Public pages of the demo site, one per template.
function ashgrovePages(): array
{
    return [
        'home' => '/',
        'home page two' => '/?page=2',
        'long post' => '/blog/a-slow-weekend-in-porto',
        'post with code' => '/blog/how-we-bake-sourdough-at-home',
        'page' => '/about',
        'child page' => '/about/our-team',
        'contact page' => '/contact',
        'archive' => '/blog',
        'category' => '/category/travel',
        'tag' => '/tag/books',
        'search' => '/search?q=bread',
        'empty search' => '/search',
    ];
}

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
});

it('has a valid manifest, settings schema and patterns', function () {
    $theme = Theme::fromDirectory(base_path('themes/ashgrove'));
    $schema = ThemeSettingsSchema::fromConfig($theme->settings);

    expect($theme->name)->toBe('Ashgrove')
        ->and($theme->parent)->toBeNull()
        ->and(array_keys($schema->fields))->toBe(['accent_color', 'heading_font', 'body_font', 'logo', 'layout_width', 'show_author_box', 'footer_text'])
        ->and(array_keys($theme->templates))->toBe(['wide', 'landing'])
        ->and(collect(app(BlockPatterns::class)->all())->pluck('name')->sort()->values()->all())
        ->toBe(['about-the-author', 'hero-intro', 'two-column-feature']);
});

it('offers the newsletter pattern only when the Newsletter plugin is on', function () {
    PluginFixtures::boot('newsletter');

    expect(collect(app(BlockPatterns::class)->all())->pluck('name'))->toContain('newsletter-call-to-action');
});

it('renders every template with the demo content', function (string $name) {
    $this->get(ashgrovePages()[$name])
        ->assertOk()
        ->assertSee('class="site-header"', false)
        ->assertSee('class="site-footer"', false)
        ->assertSee('Skip to content');
})->with(array_keys(ashgrovePages()));

it('renders the wide page template', function () {
    $page = Entry::firstWhere('slug', 'privacy-policy');
    $page->forceFill(['meta' => [...$page->meta, 'template' => 'wide']])->save();

    $this->get($page->path())->assertOk()->assertSee('page page--wide', false);
});

it('shows the newest post as the lead story, then the grid and the category strip', function () {
    $page = ashgrovePage($this->get('/')->getContent());
    $lead = $page->query('//section[contains(@class, "lead-story")]//article[contains(@class, "post-card--lead")]');
    $grid = $page->query('//ol[contains(@class, "post-grid")]/li/article');

    expect($lead->length)->toBe(1)
        ->and($lead->item(0)->textContent)->toContain('A Slow Weekend in Porto')
        ->and($lead->item(0)->textContent)->toContain('Pablo Ortega')
        ->and($grid->length)->toBe(9)
        ->and($page->query('//ol[contains(@class, "post-grid")]//a[contains(@class, "post-category")]')->length)->toBe(9)
        ->and($page->query('//ol[contains(@class, "post-grid")]//span[@class="reading-minutes"]')->length)->toBe(9)
        ->and($page->query('//nav[@aria-label="Categories"]//a')->length)->toBe(5)
        ->and($page->query('//nav[@aria-label="Pagination"]//a[@rel="next"]')->length)->toBe(1);
});

it('shows the contents, author box, tags, neighbours and related posts on a long post', function () {
    $page = ashgrovePage($this->get('/blog/a-slow-weekend-in-porto')->getContent());

    expect($page->query('//nav[contains(@class, "toc")]//a[@href="#walking-without-a-map"]')->length)->toBe(1)
        ->and($page->query('//h2[@id="walking-without-a-map"]')->length)->toBe(1)
        ->and($page->query('//aside[contains(@class, "author-box")]')->item(0)->textContent)->toContain('Pablo takes the photos')
        ->and($page->query('//img[contains(@class, "author-avatar")]')->length)->toBe(1)
        ->and($page->query('//ul[contains(@class, "tags")]//a[@rel="tag"]')->length)->toBe(2)
        ->and($page->query('//a[@rel="prev"]')->item(0)->textContent)->toContain('How We Bake Sourdough at Home')
        ->and($page->query('//a[@rel="next"]')->length)->toBe(0)
        ->and($page->query('//section[contains(@class, "related")]//article')->length)->toBe(3)
        ->and($page->query('//figure[contains(@class, "post-hero")]/figcaption')->item(0)->textContent)->toBe('The Ribeira from the Gaia side, just after sunset.')
        ->and($page->query('//figure[contains(@class, "verso-gallery")]//img')->length)->toBe(3)
        ->and($page->query('//section[@id="comments"]')->length)->toBe(1);
});

it('hides the author box when the setting is off', function () {
    $theme = app(ThemeManager::class)->active();
    app(ThemeCustomizer::class)->save($theme, ['show_author_box' => false]);

    $this->get('/blog/a-slow-weekend-in-porto')
        ->assertOk()
        ->assertDontSee('class="author-box"', false);
});

it('shows initials when the author has no photo', function () {
    User::query()->where('email', 'pablo@verso.test')->update(['avatar_media_id' => null]);

    $this->get('/blog/a-slow-weekend-in-porto')->assertSee('author-avatar--initials', false);
});

it('marks the search words in the results', function () {
    $page = ashgrovePage($this->get('/search?q=bread')->getContent());

    expect($page->query('//li[contains(@class, "search-result")]')->length)->toBe(1)
        ->and($page->query('//p[contains(@class, "search-result-excerpt")]/mark')->length)->toBe(2)
        ->and($page->query('//p[contains(@class, "search-result-excerpt")]/mark')->item(0)->textContent)->toBe('bread');
});

it('escapes the search before marking it', function () {
    $this->get('/search?q='.urlencode('<script>bread'))
        ->assertOk()
        ->assertDontSee('<script>bread', false);
});

it('shows a 404 page with a search form', function () {
    $page = ashgrovePage($this->get('/blog/a-post-that-never-was')->assertNotFound()->getContent());

    expect($page->query('//h1')->item(0)->textContent)->toBe('This page is not here')
        ->and($page->query('//form[@role="search"]')->length)->toBe(1);
});

it('has light and dark colors with a fallback and print styles', function () {
    $css = ashgrovePage($this->get('/')->getContent())->query('//head/style')->item(0)->textContent;

    expect($css)->toContain('color-scheme: light dark')
        ->toContain('light-dark(var(--paper-light), var(--paper-dark))')
        ->toContain('@media (prefers-color-scheme: dark)')
        ->toContain('@media print')
        ->toContain('.skip-link:focus')
        ->toContain(':focus-visible');
});

it('gives every image a width, a height and alt text, and only the first one a high priority', function (string $name) {
    $images = iterator_to_array(ashgrovePage($this->get(ashgrovePages()[$name])->getContent())->query('//img'));

    expect($images)->not->toBeEmpty();

    foreach ($images as $index => $image) {
        $src = $image->getAttribute('src');

        expect($image->getAttribute('width'))->not->toBe('', "{$src} has no width")
            ->and($image->getAttribute('height'))->not->toBe('', "{$src} has no height")
            ->and($image->hasAttribute('alt'))->toBeTrue("{$src} has no alt")
            ->and($image->getAttribute('fetchpriority') === 'high')->toBe($index === 0, "{$src} has the wrong priority");
    }
})->with(['home', 'long post', 'category', 'archive']);

it('loads images, fonts, styles and scripts only from the site itself', function (string $name) {
    $page = ashgrovePage($this->get(ashgrovePages()[$name])->getContent());
    $html = $page->document->saveHTML() ?: '';

    foreach ($page->query('//img/@src | //img/@srcset | //script/@src | //link[@rel="stylesheet" or @rel="preload" or @rel="icon"]/@href') as $attribute) {
        foreach (preg_split('/,\s*/', $attribute->value) ?: [] as $candidate) {
            expect(str_starts_with(trim($candidate), url('/')))->toBeTrue("{$name} loads {$candidate}");
        }
    }

    preg_match_all('/url\("([^"]+)"\)/', $html, $urls);

    foreach ($urls[1] as $url) {
        expect(str_starts_with($url, url('/theme-assets/ashgrove/')))->toBeTrue("{$name} loads {$url}");
    }
})->with(['home', 'long post', 'page', 'category', 'search']);

it('serves the theme fonts with a long cache and refuses other files', function () {
    $this->get('/theme-assets/ashgrove/fonts/newsreader-roman.woff2')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $this->get('/theme-assets/ashgrove/fonts/OFL-Newsreader.txt')->assertNotFound();
    $this->get('/theme-assets/ashgrove/theme.json')->assertNotFound();
    $this->get('/theme-assets/ashgrove/../default/theme.json')->assertNotFound();
    $this->get('/theme-assets/harbor-dusk/fonts/newsreader-roman.woff2')->assertNotFound();
});

it('changes the accent and fonts from the customizer', function () {
    $theme = app(ThemeManager::class)->active();
    app(ThemeCustomizer::class)->save($theme, ['accent_color' => '#7a2e4a', 'body_font' => 'georgia', 'heading_font' => 'georgia']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('--accent-light: #7a2e4a;')
        ->toContain("--font-body: Georgia, 'Times New Roman', Times, serif;")
        ->not->toContain('rel="preload" href="'.url('/theme-assets/ashgrove/fonts/newsreader-roman.woff2'));
});

it('works with the admin bar', function () {
    $admin = User::firstWhere('email', 'admin@verso.test');

    $this->actingAs($admin)->get('/')
        ->assertOk()
        ->assertSee('verso-adminbar', false)
        ->assertSee('class="site-header"', false);

    expect(app(Settings::class)->get('theme.active'))->toBe('ashgrove');
});
