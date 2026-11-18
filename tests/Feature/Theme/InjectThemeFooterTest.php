<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\ThemeFooterFilter;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    $this->entry = Entry::factory()->published()->by(User::factory()->author()->create())->create([
        'title' => 'Tiles of the Ribeira',
        'slug' => 'tiles-of-the-ribeira',
    ]);
    Filters::add(ThemeFooterFilter::class, fn (string $html, Request $request): string => $html.'<script>window.ribeiraStats = 1</script>');
});

// A copy of the default theme whose layout does not print <x-verso-footer />, like many third-party themes.
function useThemeWithoutFooter(): string
{
    $base = storage_path('framework/testing/footerless-themes-'.uniqid());
    File::copyDirectory(base_path('themes/default'), $base.'/default');

    $layout = $base.'/default/views/layouts/app.blade.php';
    File::put($layout, str_replace('<x-verso-footer />', '', File::get($layout)));

    config(['verso.themes.path' => str_replace(base_path().'/', '', $base)]);
    app()->forgetInstance(ThemeManager::class);

    return $base;
}

it('prints the footer html once on themes that use the component', function () {
    $content = (string) $this->get($this->entry->path())->assertOk()->getContent();

    expect(substr_count($content, 'window.ribeiraStats'))->toBe(1);
});

it('adds the footer html before </body> on themes without the component', function () {
    $base = useThemeWithoutFooter();

    try {
        $content = (string) $this->get($this->entry->path())->assertOk()->getContent();

        expect(substr_count($content, 'window.ribeiraStats'))->toBe(1)
            ->and(strpos($content, 'window.ribeiraStats'))->toBeLessThan(strripos($content, '</body>'))
            ->and(strpos($content, 'window.ribeiraStats'))->toBeGreaterThan(strpos($content, '</footer>'));
    } finally {
        File::deleteDirectory($base);
    }
});

it('keeps the added html in cached pages', function () {
    $base = useThemeWithoutFooter();
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);

    try {
        $this->get($this->entry->path())->assertHeader(PageCache::HEADER, 'MISS');
        $this->get($this->entry->path())
            ->assertHeader(PageCache::HEADER, 'HIT')
            ->assertSee('window.ribeiraStats', false);
    } finally {
        File::deleteDirectory($base);
    }
});

it('adds the footer on the home page of a footerless theme', function () {
    $base = useThemeWithoutFooter();

    try {
        $this->get('/')->assertOk()->assertSee('window.ribeiraStats', false);
    } finally {
        File::deleteDirectory($base);
    }
});

it('leaves feeds and other non html responses alone', function () {
    $base = useThemeWithoutFooter();

    try {
        $this->get('/feed')->assertOk()->assertDontSee('window.ribeiraStats', false);
    } finally {
        File::deleteDirectory($base);
    }
});

it('changes nothing when no plugin adds footer html', function () {
    Filters::clear(ThemeFooterFilter::class);
    $base = useThemeWithoutFooter();

    try {
        $content = (string) $this->get($this->entry->path())->assertOk()->getContent();

        expect($content)->toContain('</body>')->not->toContain('<script>window');
    } finally {
        File::deleteDirectory($base);
    }
});
