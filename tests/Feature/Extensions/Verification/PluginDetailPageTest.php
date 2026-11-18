<?php

use App\Models\User;
use App\Verso\Extensions\Models\PluginVerification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExtensionSandbox;
use Tests\Support\VerificationFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.plugins.composer_installed' => null, 'verso.extensions.verification.sandbox.enabled' => false]);
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    VerificationFixtures::cleanup();
    $this->sandbox->destroy();
});

it('shows a plugin detail page and runs the checks on the installed files', function () {
    VerificationFixtures::install('auto-publisher', $this->sandbox->pluginsPath());

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.show', 'auto-publisher'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/show')
            ->where('plugin.slug', 'auto-publisher')
            ->where('plugin.network', [])
            ->where('verification', null)
            ->where('capabilities.manage_users', 'Manage users')
        );

    $this->actingAs($this->admin)
        ->post(route('admin.plugins.verify', 'auto-publisher'))
        ->assertRedirect(route('admin.plugins.show', 'auto-publisher'));

    $verification = PluginVerification::query()->sole();

    expect($verification->source)->toBe('installed')
        ->and($verification->installed_at)->not->toBeNull()
        ->and($verification->risk->value)->toBe('high');

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.show', 'auto-publisher'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('verification.risk', 'high')
            ->where('verification.source', 'installed')
            ->where('verification.details.permissions.declared', ['manage_terms'])
        );

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('plugins.0.slug', 'auto-publisher')->where('plugins.0.risk', 'high'));
});

it('shows no risk for plugins that were never checked', function () {
    VerificationFixtures::install('harbor-tides', $this->sandbox->pluginsPath());

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('plugins.0.risk', null));
});

it('answers 404 for unknown plugins and keeps editors out of the detail page', function () {
    VerificationFixtures::install('harbor-tides', $this->sandbox->pluginsPath());
    $editor = User::factory()->editor()->create();

    $this->actingAs($this->admin)->get(route('admin.plugins.show', 'missing-gallery'))->assertNotFound();
    $this->actingAs($this->admin)->post(route('admin.plugins.verify', 'missing-gallery'))->assertNotFound();
    $this->actingAs($editor)->get(route('admin.plugins.show', 'harbor-tides'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.plugins.verify', 'harbor-tides'))->assertForbidden();
});
