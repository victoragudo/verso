<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\ThemeFooterFilter;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    $this->entry = Entry::factory()->published()->by(User::factory()->author()->create())->create([
        'title' => 'A Weekend in the Douro Valley',
        'slug' => 'a-weekend-in-the-douro-valley',
        'blocks' => [['id' => 'b_douro1', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Take the early train from Porto.', 'children' => []]],
    ]);
});

it('returns the html unchanged when no plugin adds anything', function () {
    expect(ThemeFooterFilter::apply('', Request::create('/')))->toBe('');
});

it('prints plugin footer html before the closing body tag', function () {
    config(['verso.page_cache.enabled' => false]);
    Filters::add(ThemeFooterFilter::class, fn (string $html, Request $request): string => $html.'<script>window.douroTrip = true</script>');

    $content = (string) $this->get($this->entry->path())->assertOk()->getContent();

    expect($content)->toContain('<script>window.douroTrip = true</script>')
        ->and(strpos($content, 'window.douroTrip'))->toBeLessThan(strpos($content, '</body>'))
        ->and(strpos($content, 'window.douroTrip'))->toBeGreaterThan(strpos($content, '</footer>'));
});

it('passes the current request to the callbacks', function () {
    config(['verso.page_cache.enabled' => false]);
    Filters::add(ThemeFooterFilter::class, fn (string $html, Request $request): string => $html.'<!-- page '.e($request->path()).' -->');

    $this->get($this->entry->path())->assertSee('<!-- page blog/a-weekend-in-the-douro-valley -->', false);
});

it('never compiles plugin html as blade', function () {
    Filters::add(ThemeFooterFilter::class, fn (string $html): string => $html.'<p>{{ $secret }} @php echo 1; @endphp</p>');

    expect(Blade::render('<x-verso-footer />'))->toBe('<p>{{ $secret }} @php echo 1; @endphp</p>');
});

it('keeps the footer html inside cached pages', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    Filters::add(ThemeFooterFilter::class, fn (string $html): string => $html.'<script>window.portoBeacon = 1</script>');

    $this->get($this->entry->path())->assertHeader(PageCache::HEADER, 'MISS');

    $this->get($this->entry->path())
        ->assertHeader(PageCache::HEADER, 'HIT')
        ->assertSee('<script>window.portoBeacon = 1</script>', false);
});
