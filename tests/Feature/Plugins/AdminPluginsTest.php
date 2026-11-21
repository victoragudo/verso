<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    PluginFixtures::use();
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Ruiz']);
});

it('lists plugins with their permissions and problems', function () {
    app(Settings::class)->set('plugins.enabled', ['event-calendar']);

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/index')
            ->has('plugins', 5)
            ->where('plugins.1.slug', 'event-calendar')
            ->where('plugins.1.status', 'enabled')
            ->where('plugins.1.permissions', ['manage_terms', 'upload_files'])
            ->where('plugins.2.status', 'incompatible')
            ->where('plugins.0.status', 'invalid')
            ->has('plugins.0.problems', 1)
            ->where('capabilities.manage_terms', 'Manage categories and tags')
            ->where('capabilities', fn ($labels) => $labels['ai.use'] === 'Use the AI assistant')
            ->where('versoVersion', config('verso.version'))
        );
});

it('names plugin permissions in the admin language', function () {
    $this->admin->forceFill(['admin_locale' => 'es'])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('capabilities.manage_terms', 'Gestionar categorías y etiquetas')
            ->where('capabilities.manage_settings', 'Gestionar todos los ajustes')
        );
});

it('shows the Plugins link in the admin nav for admins', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains('key', 'plugins')));
});

it('enables a plugin', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.plugins.index'))
        ->post(route('admin.plugins.enable', 'event-calendar'))
        ->assertRedirect(route('admin.plugins.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Event Calendar enabled.']);

    expect(app(Settings::class)->get('plugins.enabled'))->toBe(['event-calendar']);
});

it('disables a plugin', function () {
    app(Settings::class)->set('plugins.enabled', ['event-calendar']);

    $this->actingAs($this->admin)
        ->post(route('admin.plugins.disable', 'event-calendar'))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Event Calendar disabled.']);

    expect(app(Settings::class)->get('plugins.enabled'))->toBe([]);
});

it('shows an error toast when a plugin cannot be enabled', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.plugins.enable', 'future-gallery'))
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error');

    expect(app(Settings::class)->get('plugins.enabled'))->toBe([]);
});

it('returns 404 for unknown plugins', function () {
    $this->actingAs($this->admin)->post(route('admin.plugins.enable', 'photo-booth'))->assertNotFound();
    $this->actingAs($this->admin)->post(route('admin.plugins.disable', 'photo-booth'))->assertNotFound();
});

it('forbids users who cannot manage plugins', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.plugins.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.plugins.enable', 'event-calendar'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.plugins.disable', 'event-calendar'))->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.plugins.index'))->assertRedirect(route('login'));
});

describe('Plugins and themes page', function () {
    it('has tabs for installed plugins, installed themes, adding and browsing', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'plugins')
                ->where('tabs', ['plugins', 'themes', 'add', 'browse'])
                ->where('themes', [])
                ->where('add', null)
                ->where('registry', null)
            );
    });

    it('lists installed themes on the themes tab', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.index', ['tab' => 'themes']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'themes')
                ->where('themes', fn ($themes) => collect($themes)->contains(fn ($theme) => $theme['slug'] === 'default' && is_bool($theme['active'])))
            );
    });

    it('falls back to installed plugins for an unknown tab', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.plugins.index', ['tab' => 'marketplace']))
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'plugins'));
    });

    it('shows where each enabled plugin is in the sidebar, with a link', function () {
        PluginFixtures::boot('forms');

        $this->actingAs($this->admin)
            ->get(route('admin.plugins.index'))
            ->assertInertia(fn (Assert $page) => $page->where('plugins', fn ($plugins) => collect($plugins)->firstWhere('slug', 'forms')['location'] === [
                'path' => ['Tools', 'Forms'],
                'key' => 'plugin.forms',
                'href' => '/admin/forms',
            ] && collect($plugins)->firstWhere('slug', 'shop')['location']['href'] === null));
    });

    it('says where to find a plugin after enabling it and highlights it in the sidebar', function () {
        PluginFixtures::use(base_path('plugins'));

        $this->actingAs($this->admin)
            ->from(route('admin.plugins.index'))
            ->post(route('admin.plugins.enable', 'newsletter'))
            ->assertRedirect(route('admin.plugins.index'))
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Newsletter is ready. You will find it in Tools > Newsletter.',
                'action' => ['label' => 'Open', 'navKey' => 'plugin.newsletter'],
            ])
            ->assertInertiaFlash('navHighlight', 'plugin.newsletter');
    });

    it('points to the dashboard for a plugin that adds a dashboard page', function () {
        PluginFixtures::use(base_path('plugins'));

        $this->actingAs($this->admin)
            ->post(route('admin.plugins.enable', 'analytics'))
            ->assertInertiaFlash('toast.message', 'Analytics is ready. You will find it in Dashboard > Analytics.')
            ->assertInertiaFlash('navHighlight', 'analytics.overview');
    });
});
