<?php

use App\Models\User;
use App\Verso\Content\Actions\PublishEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Site\Jobs\PingIndexNow;
use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false]);
    $this->admin = User::factory()->admin()->create(['name' => 'Tomás Pires']);
    app(Settings::class)->setMany(['site.name' => 'Harbor Lane Bakery', 'site.tagline' => 'Bread from the old port']);
});

function searchForm(array $overrides = []): array
{
    return [
        'discourage' => false,
        'robots_txt' => "User-agent: *\nDisallow: /admin\nDisallow: /checkout\n",
        'title_format' => '{title}{sep}{site}',
        'title_separator' => ' · ',
        'default_description' => 'Sourdough, pastries and coffee by the river.',
        'default_image' => null,
        'schema_type' => 'organization',
        'schema_name' => 'Harbor Lane Bakery Ltd',
        'schema_logo' => null,
        'schema_same_as' => ['https://www.instagram.com/harborlanebakery'],
        'verification_google' => 'aB3dE5fG7hI9jK1lM3nO5pQ7',
        'verification_bing' => '4F2C8E1A9B7D3F5E',
        'indexnow_enabled' => false,
        'indexnow_key' => '',
        ...$overrides,
    ];
}

it('saves the search engine settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.search'), searchForm())
        ->assertRedirect(route('admin.site.permalinks.edit', ['tab' => 'search']))
        ->assertSessionHasNoErrors();

    $seo = app(SeoSettings::class);
    expect($seo->separatorSymbol())->toBe('·')
        ->and($seo->separator())->toBe(' · ')
        ->and($seo->defaultDescription())->toBe('Sourdough, pastries and coffee by the river.')
        ->and($seo->sameAs())->toBe(['https://www.instagram.com/harborlanebakery'])
        ->and($seo->verificationTags())->toBe(['google-site-verification' => 'aB3dE5fG7hI9jK1lM3nO5pQ7', 'msvalidate.01' => '4F2C8E1A9B7D3F5E']);
});

it('prints titles, default description, verification tags and the organization', function () {
    $this->actingAs($this->admin)->put(route('admin.site.permalinks.search'), searchForm(['title_format' => '{site}{sep}{title}']));
    auth()->logout();
    Entry::factory()->page()->published()->create(['title' => 'Opening Hours', 'slug' => 'hours', 'excerpt' => null]);

    $this->get('/hours')
        ->assertOk()
        ->assertSee('<title>Harbor Lane Bakery · Opening Hours</title>', false)
        ->assertSee('<meta name="description" content="Sourdough, pastries and coffee by the river.">', false)
        ->assertSee('<meta name="google-site-verification" content="aB3dE5fG7hI9jK1lM3nO5pQ7">', false)
        ->assertSee('<meta name="msvalidate.01" content="4F2C8E1A9B7D3F5E">', false)
        ->assertSee('"@type": "Organization"', false)
        ->assertSee('"name": "Harbor Lane Bakery Ltd"', false)
        ->assertSee('"https://www.instagram.com/harborlanebakery"', false);
});

it('uses the default share image when an entry has none', function () {
    $media = Media::factory()->create(['filename' => 'storefront.jpg']);
    app(Settings::class)->set(SeoSettings::DEFAULT_IMAGE, $media->id);
    Entry::factory()->page()->published()->create(['title' => 'Visit Us', 'slug' => 'visit']);

    $this->get('/visit')->assertSee('<meta property="og:image" content="'.url($media->url()).'">', false);
});

it('describes a person instead of an organization', function () {
    app(Settings::class)->setMany([SeoSettings::SCHEMA_TYPE => 'person', SeoSettings::SCHEMA_NAME => 'Helena Duarte']);

    $person = app(SeoSettings::class)->publisher('https://harborlane.example.org');

    expect($person)->toBe(['@type' => 'Person', '@id' => 'https://harborlane.example.org/#person', 'name' => 'Helena Duarte', 'url' => 'https://harborlane.example.org/']);
});

it('drops a separator left alone by an empty tagline', function () {
    app(Settings::class)->set(SeoSettings::TITLE_FORMAT, '{title}{sep}{tagline}');

    expect(app(SeoSettings::class)->title('Menu', 'Harbor Lane', null))->toBe('Menu');
});

it('validates the form', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.search'), searchForm($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'no title token' => [['title_format' => '{site}'], 'title_format'],
    'bad schema type' => [['schema_type' => 'company'], 'schema_type'],
    'bad profile link' => [['schema_same_as' => ['instagram harborlane']], 'schema_same_as.0'],
    'full meta tag pasted' => [['verification_google' => '<meta name="google-site-verification" content="abc">'], 'verification_google'],
    'missing image' => [['default_image' => 999999], 'default_image'],
    'indexnow without key' => [['indexnow_enabled' => true, 'indexnow_key' => ''], 'indexnow_key'],
    'short indexnow key' => [['indexnow_enabled' => true, 'indexnow_key' => 'abc'], 'indexnow_key'],
    'robots blocks all' => [['robots_txt' => "User-agent: *\nDisallow: /"], 'robots_txt'],
    'robots bad line' => [['robots_txt' => "User-agent: *\nDisalow /tmp"], 'robots_txt'],
    'robots unknown field' => [['robots_txt' => "User-agent: *\nNoindex: /tmp"], 'robots_txt'],
    'robots rule before agent' => [['robots_txt' => 'Disallow: /tmp'], 'robots_txt'],
    'robots relative path' => [['robots_txt' => "User-agent: *\nDisallow: tmp"], 'robots_txt'],
    'robots sitemap url' => [['robots_txt' => "User-agent: *\nSitemap: sitemap.xml"], 'robots_txt'],
]);

it('accepts comments and blank lines in robots.txt', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.search'), searchForm(['robots_txt' => "# Keep crawlers out of the cart\nUser-agent: *\nDisallow: /cart\n\nUser-agent: GPTBot\nDisallow: /\nCrawl-delay: 10\n"]))
        ->assertSessionHasNoErrors();

    expect($this->get('/robots.txt')->getContent())->toContain("User-agent: GPTBot\nDisallow: /");
});

it('hides the whole site when search engines are discouraged', function () {
    $this->actingAs($this->admin)->put(route('admin.site.permalinks.search'), searchForm(['discourage' => true]));
    auth()->logout();
    Entry::factory()->page()->published()->create(['slug' => 'menu']);

    $this->get('/menu')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    expect($this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

it('serves the IndexNow key file only for the saved key', function () {
    app(Settings::class)->setMany([SeoSettings::INDEXNOW_ENABLED => true, SeoSettings::INDEXNOW_KEY => 'b7f3c2a9d1e84f60']);

    $this->get('/b7f3c2a9d1e84f60.txt')->assertOk()->assertSeeText('b7f3c2a9d1e84f60')->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $this->get('/a1a1a1a1a1a1a1a1.txt')->assertNotFound();

    app(Settings::class)->set(SeoSettings::INDEXNOW_ENABLED, false);
    $this->get('/b7f3c2a9d1e84f60.txt')->assertNotFound();
});

it('queues an IndexNow ping when an entry is published', function () {
    Bus::fake([PingIndexNow::class]);
    app(Settings::class)->setMany([SeoSettings::INDEXNOW_ENABLED => true, SeoSettings::INDEXNOW_KEY => 'b7f3c2a9d1e84f60']);
    $entry = Entry::factory()->create(['title' => 'Winter Bread Classes', 'slug' => 'winter-bread-classes']);

    app(PublishEntry::class)->handle($entry);

    Bus::assertDispatched(PingIndexNow::class, fn (PingIndexNow $job) => $job->urls === [url('/blog/winter-bread-classes')]);
});

it('does not ping IndexNow when it is off', function () {
    Bus::fake([PingIndexNow::class]);

    app(PublishEntry::class)->handle(Entry::factory()->create());

    Bus::assertNotDispatched(PingIndexNow::class);
});

it('sends the IndexNow request', function () {
    config(['verso.seo.indexnow.endpoint' => 'https://indexnow.example.org/indexnow']);
    Http::fake(['indexnow.example.org/*' => Http::response('', 202)]);
    app(Settings::class)->setMany([SeoSettings::INDEXNOW_ENABLED => true, SeoSettings::INDEXNOW_KEY => 'b7f3c2a9d1e84f60']);

    app()->call([new PingIndexNow([url('/blog/winter-bread-classes')]), 'handle']);

    Http::assertSent(fn ($request) => $request->url() === 'https://indexnow.example.org/indexnow'
        && $request['key'] === 'b7f3c2a9d1e84f60'
        && $request['keyLocation'] === url('/b7f3c2a9d1e84f60.txt')
        && $request['urlList'] === [url('/blog/winter-bread-classes')]);
});

it('skips the IndexNow request when the site hides from search engines', function () {
    Http::fake();
    app(Settings::class)->setMany([SeoSettings::INDEXNOW_ENABLED => true, SeoSettings::INDEXNOW_KEY => 'b7f3c2a9d1e84f60', SeoSettings::DISCOURAGE => true]);

    app()->call([new PingIndexNow([url('/blog/winter-bread-classes')]), 'handle']);

    Http::assertNothingSent();
});
