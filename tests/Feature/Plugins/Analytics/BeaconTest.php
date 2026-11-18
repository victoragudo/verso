<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Cache\PageCache;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;
use Verso\Plugins\Analytics\Support\Beacon;

beforeEach(function () {
    AnalyticsPlugin::boot();

    $this->entry = Entry::factory()->published()->by(User::factory()->author()->create())->create([
        'title' => 'The Best Pasteis de Nata in Belem',
        'slug' => 'the-best-pasteis-de-nata-in-belem',
        'blocks' => [['id' => 'b_nata01', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Go early and order two.', 'children' => []]],
    ]);
});

it('is smaller than one kilobyte', function () {
    expect(strlen(app(Beacon::class)->script()))->toBeLessThan(1024);
});

it('posts to the relative endpoint with sendBeacon', function () {
    $script = app(Beacon::class)->script();

    expect($script)->toStartWith('<script>')
        ->toEndWith('</script>')
        ->toContain('n.sendBeacon("/_verso/collect"')
        ->toContain('new URLSearchParams');
});

it('checks do not track and global privacy control in the browser', function () {
    expect(app(Beacon::class)->script())
        ->toContain('n.doNotTrack=="1"')
        ->toContain('n.globalPrivacyControl');
});

it('sends only the path, the referrer host and a width bucket', function () {
    $script = app(Beacon::class)->script();

    expect($script)->toContain('p:l.pathname')
        ->toContain('new URL(d.referrer).host')
        ->toContain('w<768?"mobile":w<1024?"tablet":"desktop"')
        ->not->toContain('cookie')
        ->not->toContain('localStorage')
        ->not->toContain('l.href')
        ->not->toContain('p:l.search');
});

it('uses the breakpoints from config', function () {
    config(['analytics.breakpoints' => ['tablet' => 600, 'desktop' => 1280]]);

    expect(app(Beacon::class)->script())->toContain('w<600?"mobile":w<1280?"tablet":"desktop"');
});

it('escapes the endpoint safely', function () {
    $script = (new Beacon('/stats</script><b>', ['token'], 768, 1024))->script();

    expect($script)->not->toContain('</script><b>')
        ->toContain('</script>');
});

it('prints the beacon on public pages for guests', function () {
    config(['verso.page_cache.enabled' => false]);

    $this->get($this->entry->path())->assertOk()->assertSee('n.sendBeacon("/_verso/collect"', false);
    $this->get('/')->assertOk()->assertSee('n.sendBeacon(', false);
});

it('keeps the beacon in cached html', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);

    $this->get($this->entry->path())->assertHeader(PageCache::HEADER, 'MISS');

    $this->get($this->entry->path())
        ->assertHeader(PageCache::HEADER, 'HIT')
        ->assertSee('n.sendBeacon("/_verso/collect"', false);
});

it('leaves the beacon out for logged-in admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get($this->entry->path())
        ->assertOk()
        ->assertDontSee('n.sendBeacon(', false);
});

it('keeps the beacon for admins when the setting is off', function () {
    app(AnalyticsSettings::class)->update(30, []);

    $this->actingAs(User::factory()->admin()->create())
        ->get($this->entry->path())
        ->assertSee('n.sendBeacon(', false);
});

it('leaves the beacon out for every ignored role', function () {
    app(AnalyticsSettings::class)->update(30, ['editor', 'author']);

    $this->actingAs(User::factory()->editor()->create())
        ->get($this->entry->path())
        ->assertDontSee('n.sendBeacon(', false);

    $this->actingAs(User::factory()->author()->create())
        ->get($this->entry->path())
        ->assertDontSee('n.sendBeacon(', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get($this->entry->path())
        ->assertSee('n.sendBeacon(', false);
});

it('keeps the beacon for logged-in authors', function () {
    $this->actingAs(User::factory()->author()->create())
        ->get($this->entry->path())
        ->assertSee('n.sendBeacon(', false);
});

it('leaves the beacon out when analytics is turned off', function () {
    config(['analytics.enabled' => false, 'verso.page_cache.enabled' => false]);

    $this->get($this->entry->path())->assertOk()->assertDontSee('n.sendBeacon(', false);
});
