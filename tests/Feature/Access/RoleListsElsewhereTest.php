<?php

use App\Models\User;
use App\Verso\Access\Actions\CloneRole;
use App\Verso\Access\Actions\DeleteRole;
use App\Verso\Access\Roles\Roles;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Fields\Definitions\LocationContext;
use App\Verso\Fields\Definitions\LocationRule;
use App\Verso\Fields\Enums\LocationParam;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Mafalda Sousa']);
});

it('gives a copied role the editor tools of its source, and forgets a deleted role', function () {
    $copy = app(CloneRole::class)->handle($this->admin, app(Roles::class)->find('author'), 'Guest author');
    $access = app(EditorAccess::class);

    expect($access->roles('add_panel'))->toContain('guest_author')
        ->and($access->roles('custom_js'))->not->toContain('guest_author');

    $writer = giveExtraRoles(User::factory()->create(), 'guest_author');
    expect($access->enabled('add_panel', $writer))->toBeTrue();

    app(DeleteRole::class)->handle($this->admin, app(Roles::class)->find('guest_author'));
    expect(app(EditorAccess::class)->roles('add_panel'))->not->toContain('guest_author');
});

it('lists custom roles on the editor settings page', function () {
    customRole('seo_manager', ['manage_permalinks'], 'SEO manager');

    $this->actingAs($this->admin)
        ->get(route('admin.editor.settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('roles', fn ($roles) => collect($roles)->contains('value', 'seo_manager')));
});

it('matches user role field rules against every role of the user', function () {
    $rule = new LocationRule(LocationParam::UserRole, '==', 'fact_checker');

    expect($rule->matches(new LocationContext(role: 'author', roles: ['author', 'fact_checker'])))->toBeTrue()
        ->and($rule->matches(new LocationContext(role: 'author', roles: ['author'])))->toBeFalse();
});

it('skips analytics for anyone with an ignored role, main or extra', function () {
    PluginFixtures::boot('analytics');
    app(AnalyticsSettings::class)->update(30, ['editor']);

    $extraEditor = giveExtraRoles(User::factory()->author()->create(), 'editor');

    expect(app(AnalyticsSettings::class)->ignores($extraEditor))->toBeTrue()
        ->and(app(AnalyticsSettings::class)->ignores(User::factory()->author()->create()))->toBeFalse();
});
