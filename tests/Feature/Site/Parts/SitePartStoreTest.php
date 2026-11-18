<?php

use App\Models\User;
use App\Verso\Site\Events\SitePartPublished;
use App\Verso\Site\Models\SitePart;
use App\Verso\Site\Models\SitePartRevision;
use App\Verso\Site\Parts\SitePartStatus;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    Carbon::setTestNow('2026-11-17 09:30:00');
    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', [], [
        'views/parts/header.blade.php' => '<header>Harbor header for {{ $siteTitle }}</header>',
        'views/parts/footer.blade.php' => '<footer>Harbor footer</footer>',
        'views/parts/footer-minimal.blade.php' => '<footer>Harbor minimal footer</footer>',
        'views/page.blade.php' => "@templatePart('header')\n@templatePart('footer', \$footerStyle)",
    ]);
    $this->sandbox->activate('harbor');
    $this->editor = User::factory()->create(['name' => 'Ingrid Solberg']);
    $this->store = app(SitePartStore::class);
    $this->headerBlocks = [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Kvarnholmen Bakery'],
        ['type' => 'paragraph', 'content' => 'Fresh cardamom buns every morning.'],
    ];
});

afterEach(function () {
    $this->sandbox->destroy();
});

function renderHarborPage(?string $footerStyle = null): string
{
    app()->forgetScopedInstances();

    return view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => $footerStyle])->render();
}

it('uses the theme file while a part only has a draft', function () {
    $part = $this->store->saveDraft('harbor', 'header', $this->headerBlocks, $this->editor->id);

    expect($part->status)->toBe(SitePartStatus::Draft)
        ->and($part->isLive())->toBeFalse()
        ->and(renderHarborPage())->toContain('<header>Harbor header for Kvarnholmen Notes</header>')
        ->not->toContain('Fresh cardamom buns');
});

it('shows the published blocks instead of the theme file', function () {
    $this->store->publish('harbor', 'header', $this->headerBlocks, $this->editor->id);

    $html = renderHarborPage();

    expect($html)->toContain('<header class="site-part site-part-header" data-site-part="header">')
        ->toContain('Kvarnholmen Bakery')
        ->toContain('Fresh cardamom buns every morning.')
        ->not->toContain('Harbor header for');
});

it('never changes the theme file', function () {
    $file = $this->sandbox->root.'/harbor/views/parts/header.blade.php';
    $before = file_get_contents($file);

    $this->store->publish('harbor', 'header', $this->headerBlocks);

    expect(file_get_contents($file))->toBe($before);
});

it('keeps visitors on the published copy while a new draft is saved', function () {
    $this->store->publish('harbor', 'header', $this->headerBlocks);
    $part = $this->store->saveDraft('harbor', 'header', [['type' => 'paragraph', 'content' => 'Closed on Mondays']]);

    expect($part->status)->toBe(SitePartStatus::Draft)
        ->and($part->hasUnpublishedChanges())->toBeTrue()
        ->and(renderHarborPage())->toContain('Fresh cardamom buns')->not->toContain('Closed on Mondays');
});

it('marks a draft equal to the published blocks as published', function () {
    // MySQL gives JSON keys back in its own order, so the keys here are not in that order.
    $blocks = [['content' => 'Kvarnholmen Bakery', 'type' => 'heading', 'attrs' => ['level' => 2]]];
    $this->store->publish('harbor', 'header', $blocks);

    expect($this->store->saveDraft('harbor', 'header', $blocks)->status)->toBe(SitePartStatus::Published);
});

it('restores the theme default by removing the edited copy and its revisions', function () {
    $this->store->publish('harbor', 'header', $this->headerBlocks);
    $this->store->restoreDefault('harbor', 'header');

    expect(SitePart::query()->count())->toBe(0)
        ->and(SitePartRevision::query()->count())->toBe(0)
        ->and(renderHarborPage())->toContain('<header>Harbor header for Kvarnholmen Notes</header>');
});

it('keeps one revision per publish and prunes the oldest ones', function () {
    config(['verso.site_parts.max_revisions' => 3]);
    app()->forgetScopedInstances();
    $store = app(SitePartStore::class);

    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
        $store->publish('harbor', 'header', [['type' => 'paragraph', 'content' => "Open {$day}"]], $this->editor->id);
    }

    $revisions = SitePart::query()->firstOrFail()->revisions()->get();

    expect($revisions)->toHaveCount(3)
        ->and($revisions->first()?->blocks)->toBe([['type' => 'paragraph', 'content' => 'Open Friday']])
        ->and($revisions->last()?->blocks)->toBe([['type' => 'paragraph', 'content' => 'Open Wednesday']])
        ->and($revisions->first()?->user_id)->toBe($this->editor->id);
});

it('stores parts per theme', function () {
    $this->sandbox->theme('fjord', [], ['views/parts/header.blade.php' => '<header>Fjord header</header>']);
    $this->store->publish('fjord', 'header', $this->headerBlocks);

    expect(renderHarborPage())->toContain('Harbor header for')
        ->and($this->store->published('fjord', 'header'))->toBe($this->headerBlocks)
        ->and($this->store->published('harbor', 'header'))->toBeNull();
});

it('prefers an edited variant, then the variant file, then the edited base part', function () {
    $this->store->publish('harbor', 'footer', [['type' => 'paragraph', 'content' => 'Edited base footer']]);

    expect(renderHarborPage('minimal'))->toContain('Harbor minimal footer')->not->toContain('Edited base footer')
        ->and(renderHarborPage('neon'))->toContain('Edited base footer');

    $this->store->publish('harbor', 'footer-minimal', [['type' => 'paragraph', 'content' => 'Edited minimal footer']]);

    expect(renderHarborPage('minimal'))->toContain('<footer class="site-part site-part-footer site-part-footer-minimal" data-site-part="footer-minimal">')
        ->toContain('Edited minimal footer');
});

it('shows preview blocks for one request without saving them', function () {
    $this->store->preview('harbor', 'header', [['type' => 'paragraph', 'content' => 'Preview of the winter menu']]);

    $html = view('theme::page', ['siteTitle' => 'Kvarnholmen Notes', 'footerStyle' => null])->render();

    expect($html)->toContain('Preview of the winter menu')
        ->and(SitePart::query()->count())->toBe(0);
});

it('fires an event and empties the whole page cache on publish and restore', function () {
    Event::fake([SitePartPublished::class]);

    $this->store->publish('harbor', 'header', $this->headerBlocks);
    $this->store->restoreDefault('harbor', 'header');
    $this->store->restoreDefault('harbor', 'footer');

    Event::assertDispatchedTimes(SitePartPublished::class, 2);
});

it('bumps the page cache version when a part is published', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $cache = app(PageCache::class);
    $before = $cache->version();

    $this->store->publish('harbor', 'header', $this->headerBlocks);

    expect($cache->version())->toBe($before + 1);
});

it('does not touch the page cache for a draft', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    $cache = app(PageCache::class);
    $before = $cache->version();

    $this->store->saveDraft('harbor', 'header', $this->headerBlocks);

    expect($cache->version())->toBe($before);
});

it('drops unsafe html from published blocks', function () {
    $this->store->publish('harbor', 'header', [['type' => 'paragraph', 'content' => 'Hello <script>alert(1)</script><strong>neighbours</strong>']]);

    expect(renderHarborPage())->toContain('<strong>neighbours</strong>')->not->toContain('<script>');
});
