<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryAbility;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\EntryCapabilityFilter;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Http\Middleware\InjectAdminBar;
use Illuminate\Support\Carbon;

const ADMIN_BAR_MARKER = 'aria-label="Site administration"';

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false, 'verso.admin_bar.enabled' => true]);
    app()->forgetInstance(PageCache::class);

    $this->admin = User::factory()->admin()->create(['name' => 'Lucía Ferreira']);
    $this->post = Entry::factory()->published()->by($this->admin)->create([
        'title' => 'Night Trams of Lisbon',
        'slug' => 'night-trams-of-lisbon',
    ]);
    $this->page = Entry::factory()->page()->published()->by($this->admin)->create([
        'title' => 'About the Harbour Journal',
        'slug' => 'about',
    ]);
});

function useAdminPageCache(): void
{
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
}

it('gives the admin bar capability only to admins', function () {
    expect(Role::Admin->has(Capability::ViewAdminBar))->toBeTrue();

    foreach ([Role::Editor, Role::Author, Role::Contributor, Role::Subscriber, Role::ShopManager] as $role) {
        expect($role->has(Capability::ViewAdminBar))->toBeFalse();
    }
});

it('shows the bar right after body to admins', function () {
    $content = (string) $this->actingAs($this->admin)->get('/')->assertOk()->getContent();

    expect($content)->toContain(ADMIN_BAR_MARKER)
        ->and(substr_count($content, ADMIN_BAR_MARKER))->toBe(1)
        ->and(preg_match('/<body\b[^>]*>\s*<style>/', $content))->toBe(1)
        ->and($content)->toContain('href="'.route('admin.dashboard').'"')
        ->and($content)->toContain('Lucía Ferreira')
        ->and($content)->toContain('--verso-adminbar-height: 36px');
});

it('keeps the bar at the top while scrolling and gives sticky headers its height as offset', function () {
    $content = (string) $this->actingAs($this->admin)->get('/')->assertOk()->getContent();

    expect($content)->toMatch('/\\.verso-adminbar \\{[^}]*position: fixed;/')
        ->and($content)->toContain('--verso-adminbar-offset: var(--verso-adminbar-height);')
        ->and($content)->toContain('--verso-adminbar-offset: 0px;')
        ->and(file_get_contents(resource_path('blocks/layout-header/layout-header.css')))->toContain('var(--verso-adminbar-offset, 0px)');
});

it('puts the bar after the real body tag, never inside the theme styles', function () {
    $content = (string) $this->actingAs($this->admin)->get($this->page->path())->assertOk()->getContent();
    $head = substr($content, 0, (int) strpos($content, '</head>'));

    expect($head)->toContain('never the <body>')
        ->and($head)->not->toContain('verso-adminbar')
        ->and(strpos($content, '<style>'."\n:root {\n    --verso-adminbar-height"))->toBeGreaterThan(strpos($content, '</head>'));
});

it('finds the real body tag after comments, styles and scripts', function (string $html, string $after) {
    $position = app(InjectAdminBar::class)->bodyEnd($html);

    expect($position)->not->toBeNull()
        ->and(substr($html, 0, (int) $position))->toEndWith($after);
})->with([
    'css comment' => ['<html><head><style>/* never the <body> */ body{margin:0}</style></head><body class="home">', '<body class="home">'],
    'html comment' => ['<!-- <body> --><html><body data-page="about">', '<body data-page="about">'],
    'script text' => ['<head><script>const tag = "<body>";</script></head><BODY>', '<BODY>'],
    'noscript and template' => ['<head><noscript><body></noscript><template><body></template></head><body id="top">', '<body id="top">'],
]);

it('finds no body tag when there is only text that looks like one', function () {
    expect(app(InjectAdminBar::class)->bodyEnd('<style>/* <body> */</style><p>Harbour notes</p>'))->toBeNull()
        ->and(app(InjectAdminBar::class)->bodyEnd('<!-- <body> without an end'))->toBeNull();
});

it('hides the bar from guests', function () {
    $this->get('/')->assertOk()->assertDontSee(ADMIN_BAR_MARKER, false);
    $this->get($this->post->path())->assertOk()->assertDontSee(ADMIN_BAR_MARKER, false);
});

it('hides the bar from users without the admin role', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get('/')->assertOk()->assertDontSee(ADMIN_BAR_MARKER, false);
    $this->actingAs($user)->get($this->post->path())->assertOk()->assertDontSee(ADMIN_BAR_MARKER, false);
})->with([Role::Editor, Role::Author, Role::Contributor, Role::Subscriber]);

it('hides the bar when the admin turned it off', function () {
    $this->admin->forceFill(['show_admin_bar' => false])->save();

    $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee(ADMIN_BAR_MARKER, false);
});

it('hides the bar when the config switch is off', function () {
    config(['verso.admin_bar.enabled' => false]);

    $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee(ADMIN_BAR_MARKER, false);
});

it('uses the configured height', function () {
    config(['verso.admin_bar.height' => 44]);

    $this->actingAs($this->admin)->get('/')->assertSee('--verso-adminbar-height: 44px', false);
});

it('links to the editor of the post on a post page', function () {
    $edit = route('admin.entries.edit', ['contentType' => 'post', 'entry' => $this->post->id]);

    $this->actingAs($this->admin)->get($this->post->path())
        ->assertOk()
        ->assertSee('href="'.$edit.'"', false)
        ->assertSee('<span class="verso-adminbar__phone-only" aria-hidden="true">Edit</span><span class="verso-adminbar__phone-hidden">Edit post</span>', false);

    $this->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $this->post->id]))->assertOk();
});

it('links to the editor of the page on a page', function () {
    $edit = route('admin.entries.edit', ['contentType' => 'page', 'entry' => $this->page->id]);

    $this->actingAs($this->admin)->get('/about')
        ->assertOk()
        ->assertSee('href="'.$edit.'"', false)
        ->assertSee('<span class="verso-adminbar__phone-hidden">Edit page</span>', false);
});

it('shows no edit link on pages that are not one entry', function (string $path) {
    $this->actingAs($this->admin)->get($path)
        ->assertSee(ADMIN_BAR_MARKER, false)
        ->assertDontSee('/edit"', false);
})->with(['/', '/blog', '/search?q=tram']);

it('shows no edit link when a plugin denies the update', function () {
    Filters::add(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool => $ability === EntryAbility::Update ? false : $allowed);

    $this->actingAs($this->admin)->get($this->post->path())
        ->assertSee(ADMIN_BAR_MARKER, false)
        ->assertDontSee('/edit"', false);
});

it('lists every content type in the new menu', function () {
    $this->actingAs($this->admin)->get('/')
        ->assertSee('href="'.route('admin.entries.create', ['contentType' => 'post']).'"', false)
        ->assertSee('href="'.route('admin.entries.create', ['contentType' => 'page']).'"', false)
        ->assertSee('popovertarget="verso-adminbar-new"', false);
});

it('links to the customizer of the active theme', function () {
    $this->actingAs($this->admin)->get('/')
        ->assertSee('href="'.route('admin.themes.customize', ['theme' => 'default']).'"', false);
});

it('logs out with a post form and the csrf token', function () {
    $response = $this->actingAs($this->admin)->get('/');
    $token = session()->token();

    $response->assertSee('<form method="POST" action="'.route('logout').'">', false)
        ->assertSee('<input type="hidden" name="_token" value="'.$token.'">', false)
        ->assertSee('href="'.route('profile.edit').'"', false);
});

it('points the skip link to the main area of the theme', function () {
    $this->actingAs($this->admin)->get('/')
        ->assertSee('<a class="verso-adminbar-skip" href="#content">Skip to content</a>', false)
        ->assertDontSee('id="verso-adminbar-end"', false);
});

it('shows the bar on the not found page', function () {
    $this->actingAs($this->admin)->get('/no-such-harbour')->assertNotFound()->assertSee(ADMIN_BAR_MARKER, false);
});

it('never adds the bar to non html responses', function (string $path) {
    $this->actingAs($this->admin)->get($path)->assertDontSee('verso-adminbar', false);
})->with(['/feed', '/sitemap.xml', '/robots.txt', '/api/v1/entries/post']);

it('never adds the bar to json requests', function () {
    $this->actingAs($this->admin)->getJson($this->post->path())->assertDontSee('verso-adminbar', false);
});

it('never adds the bar to the admin or the installer', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('verso-adminbar', false);
    $this->actingAs($this->admin)->get('/install')->assertDontSee('verso-adminbar', false);
});

it('never stores the bar in the page cache', function () {
    useAdminPageCache();

    $this->actingAs($this->admin)->get($this->post->path())->assertSee(ADMIN_BAR_MARKER, false);

    expect(app(PageCache::class)->get(request()->create($this->post->path())))->toBeNull();

    auth()->logout();
    $this->flushSession();

    $this->get($this->post->path())->assertHeader(PageCache::HEADER, 'MISS')->assertDontSee(ADMIN_BAR_MARKER, false);
    $this->get($this->post->path())->assertHeader(PageCache::HEADER, 'HIT')->assertDontSee(ADMIN_BAR_MARKER, false);

    $stored = app(PageCache::class)->get(request()->create($this->post->path()));

    expect((string) $stored?->getContent())->not->toContain('verso-adminbar');
});

it('still shows the bar to admins on a page that guests have cached', function () {
    useAdminPageCache();

    $this->get($this->post->path())->assertHeader(PageCache::HEADER, 'MISS');

    $this->actingAs($this->admin)->get($this->post->path())
        ->assertOk()
        ->assertHeaderMissing(PageCache::HEADER)
        ->assertSee(ADMIN_BAR_MARKER, false)
        ->assertSee('Cache: bypass', false)
        ->assertCookie((string) config('verso.page_cache.bypass_cookie'));
});

it('never serves a cached page early to a visitor with the bypass cookie', function () {
    useAdminPageCache();

    $this->get($this->post->path())->assertHeader(PageCache::HEADER, 'MISS');

    $this->actingAs($this->admin)
        ->withUnencryptedCookie((string) config('verso.page_cache.bypass_cookie'), '1')
        ->get($this->post->path())
        ->assertHeaderMissing(PageCache::HEADER)
        ->assertSee(ADMIN_BAR_MARKER, false);
});

it('shows the cache as off when the page cache is off', function () {
    $this->actingAs($this->admin)->get('/')->assertSee('Cache: off', false);
});

it('saves the admin bar choice from the profile', function () {
    $this->actingAs($this->admin)->patch(route('profile.update'), [
        'name' => 'Lucía Ferreira',
        'email' => $this->admin->email,
        'show_admin_bar' => '0',
    ])->assertSessionHasNoErrors();

    expect($this->admin->refresh()->show_admin_bar)->toBeFalse();

    $this->actingAs($this->admin)->patch(route('profile.update'), [
        'name' => 'Lucía Ferreira',
        'email' => $this->admin->email,
    ])->assertSessionHasNoErrors();

    expect($this->admin->refresh()->show_admin_bar)->toBeFalse();
});

it('offers the admin bar choice only to users who can see the bar', function () {
    $this->actingAs($this->admin)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('canViewAdminBar', true));

    $this->actingAs(User::factory()->editor()->create())->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('canViewAdminBar', false));
});
