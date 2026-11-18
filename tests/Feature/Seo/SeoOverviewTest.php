<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Checks\BaseCheck;
use App\Verso\Seo\Audit\IssueItem;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Audit\SeoCheckRegistry;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\Site\Sitemap\SitemapSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->setMany([
        'site.name' => 'Harbor Lane Bakery',
        'site.tagline' => 'Bread and pastries from the old harbor',
        'site.url' => 'https://harborlane.example.org',
    ]);
    $this->admin = User::factory()->admin()->create();
});

function checkResult(string $key): CheckResult
{
    return app(SeoAudit::class)->report(fresh: true)->result($key);
}

function goodPost(array $attributes = []): Entry
{
    return Entry::factory()->published()->create([
        'title' => 'How we bake dark rye bread at dawn',
        'meta' => ['seo' => ['description' => 'Our bakers start at four. This is how the dark rye loaf is made, from the starter to the oven.'], 'featured_image' => ['mediaId' => 1]],
        ...$attributes,
    ]);
}

it('shows the overview with the score and the issues', function () {
    goodPost();
    Entry::factory()->published()->create(['title' => 'Hi', 'excerpt' => null, 'meta' => []]);

    $this->actingAs($this->admin)
        ->get(route('admin.seo.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/seo/overview')
            ->has('score')
            ->where('thresholds', ['good' => 70, 'ok' => 40])
            ->where('checks.0.affected', fn ($affected) => $affected > 0)
            ->where('ratings.none', 2)
            ->where('can.manage', true)
            ->has('linkCheck.status')
            ->has('notFound.top'));
});

it('lets editors in and keeps authors out', function () {
    $this->actingAs(User::factory()->editor()->create())->get(route('admin.seo.index'))->assertOk();
    $this->actingAs(User::factory()->author()->create())->get(route('admin.seo.index'))->assertForbidden();
    $this->actingAs(User::factory()->author()->create())->post(route('admin.seo.refresh'))->assertForbidden();
});

it('finds pages without a description, ignoring hidden ones', function () {
    goodPost();
    $missing = Entry::factory()->published()->create(['title' => 'Opening hours for the summer', 'excerpt' => null]);
    Entry::factory()->published()->create(['excerpt' => null, 'meta' => ['seo' => ['noindex' => true]]]);
    Entry::factory()->create(['excerpt' => null]);

    $result = checkResult('missing-description');

    expect($result->affected)->toBe(1)
        ->and($result->total)->toBe(2)
        ->and($result->items[0]->title)->toBe('Opening hours for the summer')
        ->and($result->items[0]->editUrl)->toBe(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $missing->id]));
});

it('checks title length with the title format', function () {
    goodPost();
    Entry::factory()->published()->create(['title' => 'Rye']);
    Entry::factory()->published()->create(['title' => str_repeat('Sourdough starter tips ', 4)]);

    $details = collect(checkResult('title-length')->items)->map(fn ($item) => $item->displayDetail())->all();

    expect($details)->toHaveCount(2)
        ->and($details[0])->toContain('use at least 30')
        ->and($details[1])->toContain('cut it after about 60');
});

it('finds duplicate titles and descriptions', function () {
    Entry::factory()->published()->create(['title' => 'Croissant day', 'excerpt' => 'Free croissants on Saturday.']);
    Entry::factory()->published()->create(['title' => 'croissant  DAY', 'excerpt' => 'Free croissants on Saturday.']);
    Entry::factory()->page()->published()->create(['title' => 'Contact us', 'excerpt' => 'Write to us.']);

    expect(checkResult('duplicate-titles')->affected)->toBe(2)
        ->and(checkResult('duplicate-titles')->items[0]->displayDetail())->toBe('Same title as 1 other page.')
        ->and(checkResult('duplicate-descriptions')->affected)->toBe(2);
});

it('finds posts without an image unless a default share image is set', function () {
    goodPost();
    Entry::factory()->published()->create(['meta' => []]);
    Entry::factory()->page()->published()->create(['meta' => []]);

    expect(checkResult('missing-image')->affected)->toBe(1);

    app(Settings::class)->set(SeoSettings::DEFAULT_IMAGE, Media::factory()->create()->id);

    expect(checkResult('missing-image')->affected)->toBe(0);
});

it('counts images without alt text in the library and in content', function () {
    Media::factory()->create(['alt' => 'Rye loaves cooling']);
    Media::factory()->count(2)->create(['alt' => null]);
    Media::factory()->video()->create(['alt' => null]);
    Entry::factory()->published()->create(['blocks' => [
        ['id' => 'b_img001', 'type' => 'image', 'attrs' => ['src' => 'https://cdn.example.org/oven.jpg', 'alt' => '']],
        ['id' => 'b_img002', 'type' => 'image', 'attrs' => ['mediaId' => 5, 'alt' => '']],
    ]]);

    $result = checkResult('images-without-alt');

    expect($result->affected)->toBe(3)
        ->and($result->items[0]->displayDetail())->toBe('2 images need alt text.')
        ->and($result->items[1]->displayDetail())->toContain('1 image from another site');
});

it('finds hidden pages that are in a menu', function () {
    $hidden = Entry::factory()->page()->published()->create(['title' => 'Catering', 'meta' => ['seo' => ['noindex' => true]]]);
    MenuItem::factory()->forEntry($hidden)->create();

    $result = checkResult('noindex-in-menus');

    expect($result->affected)->toBe(1)->and($result->items[0]->title)->toBe('Catering');
});

it('flags site wide switches and fixes them with one click', function () {
    app(Settings::class)->setMany([SitemapSettings::ENABLED => false, SeoSettings::DISCOURAGE => true]);

    expect(checkResult('sitemap-off')->affected)->toBe(1)
        ->and(checkResult('search-discouraged')->affected)->toBe(1);

    $this->actingAs($this->admin)->post(route('admin.seo.issues.fix', 'sitemap-off'))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.message', 'The sitemap is on.');
    $this->actingAs($this->admin)->post(route('admin.seo.issues.fix', 'search-discouraged'))->assertRedirect();

    expect(app(SitemapSettings::class)->enabled())->toBeTrue()
        ->and(app(SeoSettings::class)->discourage())->toBeFalse()
        ->and(checkResult('sitemap-off')->affected)->toBe(0);
});

it('needs the settings capability for site wide fixes', function () {
    app(Settings::class)->set(SeoSettings::DISCOURAGE, true);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.seo.issues.fix', 'search-discouraged'))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.seo.issues.fix', 'missing-description'))->assertNotFound();
    $this->actingAs($this->admin)->post(route('admin.seo.issues.fix', 'no-such-check'))->assertNotFound();

    expect(app(SeoSettings::class)->discourage())->toBeTrue();
});

it('flags a site without a tagline or default description', function () {
    expect(checkResult('no-site-description')->affected)->toBe(0);

    app(Settings::class)->set('site.tagline', '');

    expect(checkResult('no-site-description')->affected)->toBe(1);
});

it('shows link based checks as pending before the first link check', function () {
    expect(checkResult('broken-links')->pending)->toBeTrue()
        ->and(checkResult('orphan-pages')->pending)->toBeTrue()
        ->and(checkResult('slow-pages')->pending)->toBeTrue();
});

it('lists the items of one issue with filters and pages', function () {
    config(['verso.seo.audit.per_page' => 2]);
    Entry::factory()->count(3)->published()->create(['excerpt' => null]);
    Entry::factory()->page()->published()->create(['title' => 'Wholesale orders', 'excerpt' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.seo.issues.show', 'missing-description'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/seo/issue')
            ->where('check.key', 'missing-description')
            ->where('items.total', 4)
            ->has('items.data', 2)
            ->has('types', 2));

    $this->actingAs($this->admin)
        ->get(route('admin.seo.issues.show', ['check' => 'missing-description', 'type' => 'page']))
        ->assertInertia(fn (Assert $page) => $page->where('items.total', 1)->where('items.data.0.title', 'Wholesale orders'));

    $this->actingAs($this->admin)
        ->get(route('admin.seo.issues.show', ['check' => 'missing-description', 'search' => 'wholesale']))
        ->assertInertia(fn (Assert $page) => $page->where('items.total', 1));

    $this->actingAs($this->admin)->get(route('admin.seo.issues.show', 'not-a-check'))->assertNotFound();
});

it('caches the report and clears it when content changes', function () {
    $audit = app(SeoAudit::class);
    $first = $audit->report();
    Entry::factory()->published()->create(['excerpt' => null]);

    expect($audit->report()->result('missing-description')->affected)->toBe(1)
        ->and($first->result('missing-description')->affected)->toBe(0);
});

it('counts the traffic lights of the editor score', function () {
    goodPost(['meta' => ['seo' => ['score' => 88]]]);
    goodPost(['meta' => ['seo' => ['score' => 55]]]);
    goodPost(['meta' => ['seo' => ['score' => 12]]]);

    expect(app(SeoAudit::class)->report(fresh: true)->ratings)->toBe(['good' => 1, 'ok' => 1, 'poor' => 1, 'none' => 0]);
});

it('runs checks that plugins add, and survives a broken one', function () {
    $registry = app(SeoCheckRegistry::class);
    $registry->add(new class extends BaseCheck
    {
        public function key(): string
        {
            return 'opening-hours';
        }

        public function label(): string
        {
            return 'Opening hours are missing';
        }

        public function description(): string
        {
            return 'Add your opening hours.';
        }

        public function severity(): Severity
        {
            return Severity::Notice;
        }

        public function run(AuditContext $context): CheckResult
        {
            return CheckResult::of([new IssueItem('hours', 'Contact page', 'No hours listed.')], 1, 10);
        }
    }, new class extends BaseCheck
    {
        public function key(): string
        {
            return 'broken-plugin';
        }

        public function label(): string
        {
            return 'Broken';
        }

        public function description(): string
        {
            return '';
        }

        public function severity(): Severity
        {
            return Severity::Critical;
        }

        public function run(AuditContext $context): CheckResult
        {
            throw new RuntimeException('Boom');
        }
    });

    $report = app(SeoAudit::class)->report(fresh: true);

    expect($report->result('opening-hours')->affected)->toBe(1)
        ->and($report->result('broken-plugin')->pending)->toBeTrue();
});

it('refreshes the counts on request', function () {
    $this->actingAs($this->admin)->post(route('admin.seo.refresh'))->assertRedirect();
});
