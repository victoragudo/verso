<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Events\SiteSettingsUpdated;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function validSiteSettings(array $overrides = []): array
{
    return [
        'name' => 'Lisbon Food Journal',
        'tagline' => 'Recipes and markets from the Portuguese coast',
        'url' => 'https://lisbonfood.example.org/',
        'locale' => 'pt-BR',
        'timezone' => 'Europe/Lisbon',
        'posts_per_page' => 12,
        'front_page' => null,
        'comments_enabled' => true,
        'revisions_limit' => 40,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('shows the settings page with the current values', function () {
    app(Settings::class)->set('site.name', 'Porto Cycling Club');
    $about = Entry::factory()->page()->published()->create(['title' => 'About the club']);
    Entry::factory()->page()->create(['title' => 'Draft rules']);
    Entry::factory()->published()->create(['title' => 'Spring ride report']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site/settings')
            ->where('settings.name', 'Porto Cycling Club')
            ->where('settings.tagline', 'Just another Verso site')
            ->where('settings.front_page', null)
            ->where('settings.comments_enabled', true)
            ->where('settings.revisions_limit', 25)
            ->has('options.pages', 1)
            ->where('options.pages.0.id', $about->id)
            ->where('options.locales', config('verso.site.locales'))
            ->has('options.timezones')
            ->where('seoUrl', route('admin.site.permalinks.edit'))
            ->missing('settings.robots_txt')
        );
});

it('saves the settings', function () {
    Event::fake([SiteSettingsUpdated::class]);
    $about = Entry::factory()->page()->published()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.site.settings.update'), validSiteSettings(['front_page' => $about->id, 'discourage_search' => true, 'robots_txt' => 'Disallow: /']))
        ->assertRedirect(route('admin.site.settings.edit'));

    $settings = app(Settings::class);
    $settings->forgetCache();

    expect($settings->get('site.name'))->toBe('Lisbon Food Journal')
        ->and($settings->get('site.url'))->toBe('https://lisbonfood.example.org')
        ->and($settings->get('site.locale'))->toBe('pt-BR')
        ->and($settings->get('site.timezone'))->toBe('Europe/Lisbon')
        ->and($settings->get('reading.posts_per_page'))->toBe(12)
        ->and($settings->get('reading.front_page'))->toBe($about->id)
        ->and($settings->get('reading.discourage_search'))->toBeFalse()
        ->and($settings->get('content.revisions_limit'))->toBe(40)
        ->and($settings->get('seo.robots_txt'))->toBe("User-agent: *\nDisallow: /admin\n");

    Event::assertDispatched(SiteSettingsUpdated::class);
});

it('turns comments off for the whole site', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.settings.update'), validSiteSettings(['comments_enabled' => false]))
        ->assertRedirect(route('admin.site.settings.edit'));

    $settings = app(Settings::class);
    $settings->forgetCache();

    expect($settings->get('comments.enabled'))->toBeFalse();
});

it('validates the settings form', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.site.settings.update'), validSiteSettings($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'bad url' => [['url' => 'not a web address'], 'url'],
    'ftp url' => [['url' => 'ftp://files.example.org'], 'url'],
    'unknown locale' => [['locale' => 'klingon'], 'locale'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'zero posts per page' => [['posts_per_page' => 0], 'posts_per_page'],
    'too many posts per page' => [['posts_per_page' => 5000], 'posts_per_page'],
    'negative revisions' => [['revisions_limit' => -1], 'revisions_limit'],
    'missing front page' => [['front_page' => 999999], 'front_page'],
]);

it('only accepts pages as the front page', function () {
    $post = Entry::factory()->published()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.site.settings.update'), validSiteSettings(['front_page' => $post->id]))
        ->assertSessionHasErrors('front_page');
});

it('forbids users without the settings capability', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.site.settings.edit'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.site.settings.update'), validSiteSettings())->assertForbidden();

    expect(app(Settings::class)->has('site.name'))->toBeFalse();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.site.settings.edit'))->assertRedirect(route('login'));
});

it('adds a settings item to the admin nav for admins only', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains('key', 'site.settings')));

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => ! collect($nav)->contains('key', 'site.settings')));
});
