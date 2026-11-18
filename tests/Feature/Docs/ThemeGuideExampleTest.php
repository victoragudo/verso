<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\ThemeVerifier;
use App\Verso\Theme\Templates\BlockPatterns;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ThemeFixtures;
use Tests\Support\ThemeSandbox;

const THEME_GUIDE_EXAMPLE = 'lighthouse';

function themeGuideExampleFiles(): array
{
    $guide = (string) file_get_contents(base_path('docs/THEME-GUIDE.md'));
    preg_match_all('/<!-- example-file: (\S+) -->\n```[a-z]*\n(.*?)```/s', $guide, $matches, PREG_SET_ORDER);

    $files = [];

    foreach ($matches as [, $path, $content]) {
        $files[$path] = $content;
    }

    ksort($files);

    return $files;
}

function themeGuideFixtureFiles(): array
{
    $root = ThemeFixtures::path(THEME_GUIDE_EXAMPLE);
    $files = [];

    foreach (File::allFiles($root) as $file) {
        $files[str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname())] = $file->getContents();
    }

    ksort($files);

    return $files;
}

beforeEach(function () {
    $this->sandbox = null;
});

afterEach(function () {
    ThemeFixtures::cleanup();
    $this->sandbox?->destroy();
});

it('shows every file of the example theme in the guide exactly as the fixture has it', function () {
    expect(themeGuideExampleFiles())->toBe(themeGuideFixtureFiles())
        ->and(array_keys(themeGuideExampleFiles()))->toContain('theme.json', 'assets/theme.css', 'views/layouts/app.blade.php');
});

it('passes the theme checks with a low risk and only information findings', function () {
    $this->sandbox = ThemeSandbox::create();
    config(['verso.extensions.verification.sandbox.enabled' => false]);

    $report = app(ThemeVerifier::class)->verify(ThemeFixtures::withScreenshot(THEME_GUIDE_EXAMPLE));
    $problems = array_filter($report->findings, fn (Finding $finding): bool => $finding->severity->value !== 'info');

    expect($report->risk->value)->toBe('low')
        ->and($report->score)->toBe(0)
        ->and($report->details['blocking'])->toBe([])
        ->and($problems)->toBe([])
        ->and($report->details['theme']['quality'])->toBe(['seo' => true, 'head' => true, 'footer' => true, 'skipLink' => true, 'lang' => true]);
});

describe('rendering', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->sandbox = ThemeSandbox::create();
        File::copyDirectory(ThemeFixtures::path(THEME_GUIDE_EXAMPLE), $this->sandbox->root.'/'.THEME_GUIDE_EXAMPLE);
        $this->sandbox->activate(THEME_GUIDE_EXAMPLE);
    });

    it('renders every page of the site with the example theme', function (string $path, int $status) {
        $this->get($path)
            ->assertStatus($status)
            ->assertSee('<a href="#content" class="skip-link">Skip to content</a>', false)
            ->assertSee('<meta name="color-scheme" content="light dark">', false)
            ->assertSee('class="site-header"', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee('--paper-light: #fdfcfa;', false)
            ->assertDontSee('lighthouse::');
    })->with([
        'home' => ['/', 200],
        'post' => ['/blog/a-slow-weekend-in-porto', 200],
        'page' => ['/about', 200],
        'archive' => ['/blog', 200],
        'category' => ['/category/travel', 200],
        'tag' => ['/tag/books', 200],
        'search' => ['/search?q=bread', 200],
        'empty search' => ['/search', 200],
        'not found' => ['/no-such-page-at-the-harbor', 404],
    ]);

    it('renders the wide page template', function () {
        $page = Entry::firstWhere('slug', 'about');
        $page->forceFill(['meta' => [...($page->meta ?? []), 'template' => 'wide']])->save();

        $this->get($page->path())->assertOk()->assertSee('class="wrap entry entry--wide"', false);
    });

    it('prints changed customizer values as CSS variables', function () {
        app(Settings::class)->set('theme.lighthouse.text_width', '65ch');
        app(Settings::class)->set('theme.lighthouse.accent_color', '#7c2d12');

        $this->get('/')->assertOk()
            ->assertSee('--measure: 65ch;', false)
            ->assertSee('--accent-light: #7c2d12;', false);
    });

    it('hides excerpts in lists when the toggle setting is off', function () {
        $excerpt = Entry::query()->ofType('post')->published()->whereNotNull('excerpt')->where('excerpt', '!=', '')->latest('published_at')->value('excerpt');

        $this->get('/')->assertOk()->assertSee($excerpt);

        app(Settings::class)->set('theme.lighthouse.show_excerpts', false);

        $this->get('/')->assertOk()->assertDontSee($excerpt);
    });

    it('offers its block pattern in the editor', function () {
        expect(collect(app(BlockPatterns::class)->all())->pluck('name'))->toContain('call-to-action');
    });

    it('uses the theme translations in the site language', function () {
        app(Settings::class)->set('site.locale', 'es');

        $this->get('/no-such-page-at-the-harbor')
            ->assertNotFound()
            ->assertSee('Saltar al contenido')
            ->assertSee('Página no encontrada');
    });
});
