<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Http\Middleware\MarkPageCacheBypass;
use App\Verso\Theme\Http\Middleware\ServeCachedPage;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 09:30:00');
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);

    $this->author = User::factory()->author()->create(['name' => 'Inés Navarro']);
    Entry::factory()->published()->by($this->author)->create([
        'title' => 'Night Trains Across the Alps',
        'slug' => 'night-trains-across-the-alps',
    ]);
    $this->path = '/blog/night-trains-across-the-alps';
});

it('serves a hit before the session starts, without cookies', function () {
    $this->get($this->path)->assertHeader(PageCache::HEADER, 'MISS');

    $hit = $this->get($this->path)->assertHeader(PageCache::HEADER, 'HIT');

    expect($hit->headers->getCookies())->toBe([])
        ->and($hit->getContent())->toContain('Night Trains Across the Alps');
});

it('puts the early middleware before the web group on public routes', function () {
    $middleware = Route::getRoutes()->getByName('theme.page')->gatherMiddleware();

    expect($middleware[0])->toBe('verso.cache.early')
        ->and(array_search('web', $middleware, true))->toBe(1)
        ->and(app('router')->getMiddleware()['verso.cache.early'])->toBe(ServeCachedPage::class);
});

it('sends the same ETag and Last-Modified on the miss and on the hit', function () {
    $miss = $this->get($this->path);
    $hit = $this->get($this->path);

    expect($miss->headers->get('ETag'))->toStartWith('"')
        ->and($hit->headers->get('ETag'))->toBe($miss->headers->get('ETag'))
        ->and($hit->headers->get('Last-Modified'))->toBe('Mon, 16 Nov 2026 09:30:00 GMT')
        ->and($miss->headers->get('Last-Modified'))->toBe('Mon, 16 Nov 2026 09:30:00 GMT');
});

it('lets browsers keep a hit but check it again', function () {
    $this->get($this->path);

    $cacheControl = $this->get($this->path)->headers->get('Cache-Control');

    expect($cacheControl)->toContain('public')->toContain('must-revalidate')->toContain('max-age=0');
});

it('uses the configured Cache-Control header', function () {
    config(['verso.page_cache.cache_control' => 'public, max-age=60']);
    app()->forgetInstance(PageCache::class);
    $this->get($this->path);

    expect($this->get($this->path)->headers->get('Cache-Control'))->toBe('max-age=60, public');
});

it('answers 304 when the ETag still matches', function () {
    $etag = $this->get($this->path)->headers->get('ETag');

    $response = $this->get($this->path, ['If-None-Match' => $etag]);

    $response->assertStatus(304)->assertHeader(PageCache::HEADER, 'HIT');
    expect($response->getContent())->toBe('');
});

it('answers 304 when the page did not change since the given date', function () {
    $this->get($this->path);

    $this->get($this->path, ['If-Modified-Since' => 'Mon, 16 Nov 2026 10:00:00 GMT'])->assertStatus(304);
});

it('sends the full page when the ETag is old', function () {
    $this->get($this->path);

    $this->get($this->path, ['If-None-Match' => '"an-old-version"'])
        ->assertOk()
        ->assertSee('Night Trains Across the Alps');
});

it('gives signed in visitors the bypass cookie and takes it away after logout', function () {
    $this->actingAs($this->author)->get($this->path)->assertCookie('verso_nocache');

    auth()->logout();

    $this->withUnencryptedCookie('verso_nocache', '1')->get($this->path)->assertCookieExpired('verso_nocache');
});

it('never sends a stored page early to a visitor with the bypass cookie', function () {
    $this->get($this->path);
    $this->actingAs($this->author);

    $this->withUnencryptedCookie('verso_nocache', '1')->get($this->path)
        ->assertOk()
        ->assertHeaderMissing(PageCache::HEADER);
});

it('skips the early cache for visitors with a remember me cookie', function () {
    $this->get($this->path);

    $cache = app(PageCache::class);
    $request = Request::create($this->path);
    $request->cookies->set('remember_web_59ba36addc2b2f9401580f014c7f58ea4e30989d', 'token');

    expect($cache->acceptsEarly($request))->toBeFalse()
        ->and($cache->acceptsEarly(Request::create($this->path)))->toBeTrue();
});

it('skips the early cache for unknown query parameters and posts', function () {
    $cache = app(PageCache::class);

    expect($cache->acceptsEarly(Request::create($this->path.'?utm_source=newsletter')))->toBeFalse()
        ->and($cache->acceptsEarly(Request::create($this->path.'?page=2')))->toBeTrue()
        ->and($cache->acceptsEarly(Request::create($this->path, 'POST')))->toBeFalse();
});

it('gives the bypass cookie to a visitor with a notice waiting', function () {
    Route::middleware('web')->get('/_test/flash-notice', function () {
        session()->flash('status', 'Thanks, your comment is waiting for approval.');

        return response('ok');
    });

    $this->get('/_test/flash-notice')->assertCookie('verso_nocache');
});

it('empties the cache when a redirect is added', function () {
    $this->get($this->path);

    Redirect::point($this->path, '/blog');

    $this->get($this->path)->assertRedirect('/blog');
});

it('keeps the bypass middleware in the web group when a new HTTP kernel is made', function () {
    app()->forgetInstance(Kernel::class);
    app(Kernel::class);

    expect(app('router')->getMiddlewareGroups()['web'])->toContain(MarkPageCacheBypass::class);
});

it('empties the cache when a setting changes while settings are not in the cache store', function () {
    $this->get($this->path)->assertHeader(PageCache::HEADER, 'MISS');

    // The settings entry expired, so forgetting it finds nothing to remove.
    Cache::store()->getStore()->forget((string) config('verso.settings.cache_key'));

    app(Settings::class)->set('site.name', 'Rail Diaries');

    $this->get($this->path)->assertHeader(PageCache::HEADER, 'MISS')->assertSee('Rail Diaries');
});
