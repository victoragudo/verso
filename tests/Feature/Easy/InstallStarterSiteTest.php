<?php

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Actions\InstallStarterSite;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Starters\StarterSiteRegistry;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\PluginManager;
use App\Verso\Site\Models\Menu;
use Tests\Support\EasyFixtures;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    EasyFixtures::fastImages();
    PluginFixtures::use(base_path('plugins'));
    $this->admin = User::factory()->admin()->create(['name' => 'Clara Montes']);
});

dataset('core starters', ['blog', 'business', 'portfolio', 'restaurant', 'shop', 'event', 'personal']);

it('creates the published pages, menus and settings of every starter', function (string $key) {
    $site = app(StarterSiteRegistry::class)->get($key);

    $report = app(InstallStarterSite::class)->handle($this->admin, $key);

    foreach ($site->pages as $page) {
        $entry = Entry::query()->ofType($page->type)->where('slug', $page->slug)->firstOrFail();

        expect($entry->title)->toBe($page->title)
            ->and($entry->status)->toBe(EntryStatus::Published)
            ->and($entry->meta[InstallStarterSite::META_KEY])->toBe($key)
            ->and($entry->blocks)->not->toBeEmpty()
            ->and(EasyFixtures::blockError($entry->blocks, $this->admin))->toBeNull();
    }

    $primary = Menu::forLocation('primary');
    $settings = app(Settings::class);

    expect($report->created)->toHaveCount(count($site->pages))
        ->and($report->updated)->toBe([])
        ->and($primary?->name)->toBe($site->label.' main menu')
        ->and($primary?->items()->count())->toBeGreaterThan(0)
        ->and(Menu::forLocation('footer')?->items()->count())->toBeGreaterThan(0)
        ->and($settings->get('site.tagline'))->toBe($site->settings['site.tagline'])
        ->and($settings->get(EasyState::STARTER))->toBe($key)
        ->and($settings->get(EasyState::STARTED_AT))->toBeString()
        ->and(Media::query()->where('path', 'like', "easy/{$key}/%")->count())->toBe(count($site->images));

    $front = $site->frontPage();

    if ($front === null) {
        expect($settings->get('reading.front_page'))->toBeNull();
    } else {
        expect((int) $settings->get('reading.front_page'))->toBe(Entry::query()->ofType('page')->where('slug', $front->slug)->value('id'))
            ->and($report->frontPageId)->toBe((int) $settings->get('reading.front_page'));
    }
})->with('core starters');

it('turns on the plugins of the starter', function (string $key, array $plugins) {
    $report = app(InstallStarterSite::class)->handle($this->admin, $key);

    foreach ($plugins as $slug) {
        expect(app(PluginManager::class)->isEnabled($slug))->toBeTrue();
    }

    expect($report->pluginsEnabled)->toBe($plugins)
        ->and($report->pluginsMissing)->toBe([]);
})->with([
    'shop' => ['shop', ['shop', 'forms']],
    'restaurant' => ['restaurant', ['forms']],
    'blog' => ['blog', ['newsletter']],
]);

it('puts the contact form on the contact page once Forms is on', function () {
    app(InstallStarterSite::class)->handle($this->admin, 'restaurant');

    $blocks = json_encode(Entry::query()->ofType('page')->where('slug', 'visit-us')->firstOrFail()->blocks);

    expect($blocks)->toContain('"type":"form"')
        ->and($blocks)->toContain('"title":"Book a table"');
});

it('shows the shop products and the shop menu link', function () {
    app(InstallStarterSite::class)->handle($this->admin, 'shop');

    $home = json_encode(Entry::query()->ofType('page')->where('slug', 'home')->firstOrFail()->blocks);
    $urls = Menu::forLocation('primary')->items()->pluck('url')->filter()->values()->all();

    expect($home)->toContain('"type":"shop-product-grid"')
        ->and($urls)->toBe([route('theme.shop.index', absolute: false)]);
});

it('skips plugins that are not installed and their sections', function () {
    PluginFixtures::use(PluginFixtures::path());

    $report = app(InstallStarterSite::class)->handle($this->admin, 'shop');
    $home = json_encode(Entry::query()->ofType('page')->where('slug', 'home')->firstOrFail()->blocks);

    expect($report->pluginsMissing)->toBe(['shop', 'forms'])
        ->and($report->created)->toContain('Home', 'About')
        ->and($home)->not->toContain('shop-product-grid')
        ->and(Menu::forLocation('primary')->items()->whereNull('url')->count())->toBe(3)
        ->and(Menu::forLocation('primary')->items()->count())->toBe(3);
});

it('updates its own pages when run twice and never duplicates them', function () {
    $install = app(InstallStarterSite::class);
    $install->handle($this->admin, 'business');
    $firstIds = Entry::query()->ofType('page')->orderBy('id')->pluck('id')->all();

    $report = $install->handle($this->admin, 'business');

    expect(Entry::query()->ofType('page')->orderBy('id')->pluck('id')->all())->toBe($firstIds)
        ->and($report->created)->toBe([])
        ->and($report->updated)->toBe(['Home', 'Services', 'About', 'Contact'])
        ->and(Menu::query()->where('name', 'Business main menu')->count())->toBe(1)
        ->and(Media::query()->where('path', 'like', 'easy/business/%')->count())->toBe(3);
});

it('keeps the pages the site already had and reports them', function () {
    $own = app(CreateEntry::class)->handle($this->admin, 'page', [
        'title' => 'Contact Clara',
        'slug' => 'contact',
        'status' => 'published',
        'blocks' => [['id' => 'b_own1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Call me on Fridays.</p>', 'children' => []]],
    ]);

    $report = app(InstallStarterSite::class)->handle($this->admin, 'personal');

    expect($report->skipped)->toBe(['Contact Clara'])
        ->and($report->created)->toBe(['Home'])
        ->and($own->fresh()->blocks[0]['content'])->toBe('<p>Call me on Fridays.</p>')
        ->and(Entry::query()->ofType('page')->where('slug', 'contact')->count())->toBe(1)
        ->and(Menu::forLocation('primary')->items()->pluck('entry_id')->all())->toContain($own->id)
        ->and($report->message())->toContain('We kept your own Contact Clara.');
});

it('switches to another starter and moves the menus to it', function () {
    $install = app(InstallStarterSite::class);
    $install->handle($this->admin, 'portfolio');

    $report = $install->handle($this->admin, 'blog');

    expect(Menu::forLocation('primary')?->name)->toBe('Blog main menu')
        ->and(Menu::query()->where('name', 'Portfolio main menu')->value('location'))->toBeNull()
        ->and(app(Settings::class)->get('reading.front_page'))->toBeNull()
        ->and(app(EasyState::class)->starter())->toBe('blog')
        ->and($report->updated)->toBe(['About', 'Contact']);
});

it('uses the full width page template of the active theme', function () {
    app(InstallStarterSite::class)->handle($this->admin, 'personal');

    expect(Entry::query()->ofType('page')->where('slug', 'home')->firstOrFail()->meta['template'] ?? null)->toBe('full-width');
});

it('still builds the pages when pictures cannot be painted', function () {
    config(['verso.easy.image_width' => 0, 'verso.easy.portrait_size' => -5]);

    $report = app(InstallStarterSite::class)->handle($this->admin, 'personal');

    expect($report->created)->toBe(['Home', 'Contact'])
        ->and(EasyFixtures::blockError(Entry::query()->ofType('page')->where('slug', 'home')->firstOrFail()->blocks))->toBeNull();
});

it('turns simple mode on as the site default, but keeps a saved default', function () {
    $simple = app(SimpleMode::class);

    app(InstallStarterSite::class)->handle($this->admin, 'personal');
    expect($simple->siteDefault())->toBeTrue()->and($simple->isOn($this->admin))->toBeTrue();

    $simple->setSiteDefault(false);
    app(InstallStarterSite::class)->handle($this->admin, 'blog');
    expect($simple->siteDefault())->toBeFalse();
});
