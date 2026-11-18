<?php

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Install\InstallState;
use App\Verso\Install\Pipeline\InstallProgress;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EasyFixtures;
use Tests\Support\InstallFixtures;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    EasyFixtures::fastImages();
    PluginFixtures::use(base_path('plugins'));
    $this->admin = User::factory()->admin()->create(['name' => 'Inês Carvalho']);
});

it('sends guests to the login page', function () {
    $this->get(route('admin.easy.start'))->assertRedirect(route('login'));
    $this->post(route('admin.easy.start.store'), ['starter' => 'restaurant'])->assertRedirect(route('login'));
});

it('is only for people who manage the settings', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.easy.start'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.easy.start.store'), ['starter' => 'restaurant'])->assertForbidden();

    expect(Entry::query()->count())->toBe(0);
});

it('shows every starter site with its pages', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.easy.start'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/easy/start')
            ->has('starters', 7)
            ->where('starters.0.key', 'blog')
            ->where('starters.3.key', 'restaurant')
            ->where('starters.3.pages.0.title', 'Home')
            ->where('starters.3.pages.0.front', true)
            ->where('current', null)
            ->where('hasPages', false)
        );
});

it('tells the page when the site already has pages and which starter was chosen', function () {
    app(CreateEntry::class)->handle($this->admin, 'page', ['title' => 'Our story', 'status' => 'published']);
    app(EasyState::class)->rememberStarter('portfolio');

    $this->actingAs($this->admin)
        ->get(route('admin.easy.start'))
        ->assertInertia(fn (Assert $page) => $page->where('hasPages', true)->where('current', 'portfolio'));
});

it('installs the chosen starter and opens "Make it yours" with a message', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.easy.start.store'), ['starter' => 'restaurant'])
        ->assertRedirect(route('admin.easy.style'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Your site is ready: 4 pages from the "Restaurant or cafe" starter.']);

    expect(Entry::query()->ofType('page')->pluck('slug')->all())->toContain('home', 'menu', 'about', 'visit-us')
        ->and(app(EasyState::class)->starter())->toBe('restaurant');
});

it('asks for a known starter', function (array $data) {
    $this->actingAs($this->admin)
        ->from(route('admin.easy.start'))
        ->post(route('admin.easy.start.store'), $data)
        ->assertRedirect(route('admin.easy.start'))
        ->assertSessionHasErrors('starter');

    expect(Entry::query()->count())->toBe(0);
})->with([
    'nothing picked' => [[]],
    'unknown starter' => [['starter' => 'casino']],
    'not a text' => [['starter' => ['restaurant']]],
]);

it('adds Guided start to the Design part of the admin menu for admins only', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.easy.start'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains(
            fn ($item) => $item['key'] === 'easy-start' && $item['group'] === 'design' && in_array('ready made site', $item['keywords'], true),
        )));

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => ! collect($nav)->contains('key', 'easy-start')));
});

it('invites admins on the dashboard and stays as a quiet link after a starter', function () {
    $notice = fn () => collect($this->actingAs($this->admin)->get(route('admin.dashboard'))->viewData('page')['props']['notices'])->firstWhere('key', 'easy-start');

    expect($notice())->toMatchArray(['title' => 'Start with a ready made site', 'tone' => 'info', 'href' => '/admin/easy/start', 'icon' => 'starter']);

    app(EasyState::class)->rememberStarter('blog');

    expect($notice())->toMatchArray(['title' => 'Want to try another ready made site?', 'tone' => 'neutral', 'href' => '/admin/easy/start']);
});

it('hides the dashboard invitation from editors', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notices', fn ($notices) => ! collect($notices)->contains('key', 'easy-start')));
});

describe('after the install', function () {
    beforeEach(function () {
        InstallFixtures::useFreshLockFile();
        app(InstallState::class)->lock();
        app(InstallProgress::class)->rememberLogin($this->admin->id, 'harbor-lane-nonce');
        $this->link = URL::temporarySignedRoute('verso.welcome', now()->addMinutes(5), ['user' => $this->admin->id, 'nonce' => 'harbor-lane-nonce'], absolute: false);
    });

    it('sends the new admin to the guided start and keeps the welcome message', function () {
        $this->get($this->link)
            ->assertRedirect(route('admin.easy.start'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Verso is installed. Welcome to your new site!']);

        $this->assertAuthenticatedAs($this->admin);
    });

    it('opens the dashboard when a starter was already chosen', function () {
        app(EasyState::class)->rememberStarter('business');

        $this->get($this->link)->assertRedirect(route('admin.dashboard'));
    });

    it('opens the dashboard when the guided start is turned off', function () {
        config(['verso.easy.guided_start_after_install' => false]);

        $this->get($this->link)->assertRedirect(route('admin.dashboard'));
    });
});
