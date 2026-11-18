<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Settings\Settings;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Jobs\CheckLinks;
use App\Verso\Seo\Links\CrawlState;
use App\Verso\Seo\Links\LinkChecker;
use App\Verso\Seo\Links\LinkStatus;
use App\Verso\Seo\Models\PageReport;
use App\Verso\Seo\Models\SeoLink;
use App\Verso\Site\Models\MenuItem;
use App\Verso\System\Server\RuntimeLimits;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    app(Settings::class)->setMany(['site.name' => 'Harbor Lane Bakery', 'site.url' => 'http://localhost']);
});

function linkParagraph(string $html): array
{
    return [['id' => 'b_'.substr(md5($html), 0, 6), 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>'.$html.'</p>']];
}

it('checks links of all published content in batches', function () {
    config(['verso.seo.crawl.batch_size' => 2]);
    $rye = Entry::factory()->published()->create(['slug' => 'dark-rye']);
    $hours = Entry::factory()->page()->published()->create(['slug' => 'hours']);
    Redirect::point('/old-menu', '/hours');
    $source = Entry::factory()->published()->create(['slug' => 'weekly-news', 'blocks' => linkParagraph(
        'Read about <a href="/blog/dark-rye">our rye</a>, the <a href="http://localhost/hours">opening hours</a>, '
        .'the <a href="/old-menu">old menu</a>, a <a href="/blog/croissant-recipe">lost recipe</a>, '
        .'<a href="https://flour.example.org/rye">our miller</a>, <a href="mailto:hello@harborlane.example.org">email</a> and <a href="#top">top</a>.'
    )]);
    Entry::factory()->create(['blocks' => linkParagraph('<a href="/nowhere">draft link</a>')]);

    Artisan::call('verso:seo-check-links');

    $links = SeoLink::query()->where('source_entry_id', $source->id)->pluck('status', 'path')->map(fn (LinkStatus $status): string => $status->value)->all();
    $report = PageReport::query()->find($source->id);

    expect($links)->toBe([
        '/blog/dark-rye' => 'ok',
        '/hours' => 'ok',
        '/old-menu' => 'redirect',
        '/blog/croissant-recipe' => 'broken',
    ])
        ->and(SeoLink::query()->where('path', '/blog/dark-rye')->value('target_entry_id'))->toBe($rye->id)
        ->and(SeoLink::query()->where('path', '/hours')->value('target_entry_id'))->toBe($hours->id)
        ->and($report->internal_links)->toBe(4)
        ->and($report->external_links)->toBe(1)
        ->and($report->broken_links)->toBe(1)
        ->and(PageReport::query()->count())->toBe(3)
        ->and(app(CrawlState::class)->current())->toMatchArray(['status' => 'done', 'total' => 3, 'checked' => 3]);
});

it('feeds the broken links, orphan pages and slow pages checks', function () {
    config(['verso.seo.crawl.slow_ms' => 100]);
    $linked = Entry::factory()->published()->create(['slug' => 'linked-post', 'excerpt' => 'A post someone links to.']);
    $inMenu = Entry::factory()->page()->published()->create(['slug' => 'about', 'excerpt' => 'Who we are.']);
    $orphan = Entry::factory()->published()->create(['title' => 'Nobody links here', 'slug' => 'lonely', 'excerpt' => 'Alone.']);
    MenuItem::factory()->forEntry($inMenu)->create();
    $source = Entry::factory()->published()->create(['slug' => 'hub', 'excerpt' => 'Hub.', 'blocks' => linkParagraph('<a href="/blog/linked-post">x</a> <a href="/blog/gone">y</a>')]);
    app(Settings::class)->set('reading.front_page', $source->id);

    Artisan::call('verso:seo-check-links');
    PageReport::query()->whereKey($linked->id)->update(['render_ms' => 450]);

    $report = app(SeoAudit::class)->report(fresh: true);

    expect($report->result('broken-links')->affected)->toBe(1)
        ->and($report->result('broken-links')->items[0]->displayDetail())->toBe('Links to /blog/gone.')
        ->and(collect($report->result('orphan-pages')->items)->pluck('title')->all())->toBe(['Nobody links here'])
        ->and($report->result('slow-pages')->items[0]->displayDetail())->toBe('Content took 450 ms to build.');
});

it('starts only one check at a time and forgets entries that were unpublished', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.seo.link-check'))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'success');
    $this->actingAs($admin)->post(route('admin.seo.link-check'))->assertSessionHas('inertia.flash_data.toast.type', 'info');

    Queue::assertPushed(CheckLinks::class, 1);
});

it('drops results of entries that are no longer published', function () {
    $post = Entry::factory()->published()->create();
    Artisan::call('verso:seo-check-links');
    expect(PageReport::query()->count())->toBe(1);

    $post->forceFill(['status' => 'draft'])->save();
    (new CheckLinks)->handle(app(LinkChecker::class), app(CrawlState::class), app(RuntimeLimits::class), app(SeoAudit::class));

    expect(PageReport::query()->count())->toBe(0);
});

it('can restart a check that stopped long ago', function () {
    config(['verso.seo.crawl.stale_after' => 60]);
    $state = app(CrawlState::class);
    $state->start(10);

    expect($state->start(10))->toBeFalse();

    $this->travel(5)->minutes();

    expect($state->isRunning())->toBeFalse()->and($state->start(10))->toBeTrue();
});

it('needs the manage capability to start a check', function () {
    $this->actingAs(User::factory()->author()->create())->post(route('admin.seo.link-check'))->assertForbidden();
});
