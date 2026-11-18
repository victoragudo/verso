<?php

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Easy\Simple\SimpleModeNav;
use App\Verso\Plugins\Slots\AdminSlot;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Tests\Support\SharedSlots;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Vidal', 'email' => 'marta@harborlane.example']);
});

function navKeys(User $user): array
{
    return array_column(AdminNav::forUser($user), 'key');
}

it('shows every tool to people who never turned simple mode on', function () {
    expect(navKeys($this->admin))->toContain('themes', 'plugins', 'users', 'editor.code', 'site.permalinks', 'terms.category')
        ->and(collect(AdminNav::forUser($this->admin))->firstWhere('key', 'site.editor')['label'])->toBe('Site Editor');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('simpleMode', false)->where('routeName', 'admin.dashboard'));
});

it('keeps only the main tools with plain names in simple mode', function () {
    app(SimpleMode::class)->set($this->admin, true);
    $nav = collect(AdminNav::forUser($this->admin->fresh()));

    expect($nav->pluck('key')->all())->toBe([
        'dashboard', 'entries.post', 'entries.page', 'media', 'comments',
        'easy-start', 'easy-style', 'site.editor', 'site.menus', 'easy-details', 'site.settings',
    ])
        ->and($nav->firstWhere('key', 'site.editor')['label'])->toBe('Header and footer')
        ->and($nav->firstWhere('key', 'site.editor')['keywords'])->toContain('Site Editor')
        ->and($nav->firstWhere('key', 'entries.post')['label'])->toBe('Blog posts')
        ->and($nav->firstWhere('key', 'media')['label'])->toBe('Photos and files')
        ->and($nav->firstWhere('key', 'easy-details')['group'])->toBe(NavGroup::Settings->value);
});

it('shares the short menu and the simple mode flag with the admin pages', function () {
    AdminPreferences::of($this->admin)->setSimpleMode(true);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('simpleMode', true)
            ->where('versoNav', fn ($nav): bool => ! collect($nav)->contains('key', 'plugins') && collect($nav)->contains('label', 'Header and footer'))
        );
});

it('keeps hidden pages open by their address', function () {
    AdminPreferences::of($this->admin)->setSimpleMode(true);

    $this->actingAs($this->admin)->get(route('admin.themes.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.site.permalinks.edit'))->assertOk();
});

it('starts people in simple mode on sites with the default on', function () {
    app(SimpleMode::class)->setSiteDefault(true);
    $editor = User::factory()->editor()->create();

    expect(navKeys($editor))->not->toContain('terms.category')->toContain('entries.page');
});

it('keeps sidebar items a plugin allows', function () {
    AdminPreferences::of($this->admin)->setSimpleMode(true);
    AdminNav::add(new NavItem('bakery.orders', 'Bread orders', 'admin.dashboard', group: NavGroup::Tools));
    AdminNav::add(new NavItem('bakery.ovens', 'Oven planner', 'admin.dashboard', group: NavGroup::Tools));
    app(SimpleModeNav::class)->allow('bakery.orders');

    expect(navKeys($this->admin->fresh()))->toContain('bakery.orders')->not->toContain('bakery.ovens');
});

it('keeps the messages of the Forms plugin in simple mode', function () {
    PluginFixtures::boot('forms');
    AdminPreferences::of($this->admin)->setSimpleMode(true);
    $nav = collect(AdminNav::forUser($this->admin->fresh()));

    expect($nav->pluck('key'))->toContain('plugin.forms', 'forms.messages')->not->toContain('forms.forms', 'forms.settings')
        ->and($nav->firstWhere('key', 'plugin.forms')['href'])->toBe($nav->firstWhere('key', 'forms.messages')['href']);
});

describe('the switch', function () {
    it('needs a signed in person', function () {
        $this->put(route('admin.easy.simple-mode.update'), ['enabled' => true])->assertRedirect(route('login'));
    });

    it('turns simple mode on and off for the signed in person only', function () {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->from(route('admin.dashboard'))
            ->put(route('admin.easy.simple-mode.update'), ['enabled' => true])
            ->assertRedirect(route('admin.dashboard'))
            ->assertInertiaFlash('toast.type', 'success');

        expect(AdminPreferences::of($editor->fresh())->simpleMode())->toBeTrue()
            ->and(AdminPreferences::of($this->admin->fresh())->simpleMode())->toBeNull();

        $this->actingAs($editor)->put(route('admin.easy.simple-mode.update'), ['enabled' => false]);

        expect(AdminPreferences::of($editor->fresh())->simpleMode())->toBeFalse();
    });

    it('needs a yes or no answer', function () {
        $this->actingAs($this->admin)->put(route('admin.easy.simple-mode.update'), ['enabled' => 'maybe'])->assertSessionHasErrors('enabled');
        $this->actingAs($this->admin)->put(route('admin.easy.simple-mode.update'))->assertSessionHasErrors('enabled');
    });

    it('shows on the profile page with the current state', function () {
        app(SimpleMode::class)->setSiteDefault(true);
        $item = SharedSlots::item($this->actingAs($this->admin)->get(route('profile.edit')), AdminSlot::ProfileSections, 'easy.simple-mode');

        expect($item['component'])->toBe('easy.simple-mode')
            ->and($item['props'])->toBe(['enabled' => true, 'siteDefault' => true]);
    });
});

it('offers "Edit on the page" in the page list in simple mode only', function () {
    app(CreateEntry::class)->handle($this->admin, 'page', ['title' => 'Our bakery', 'status' => 'published']);
    $list = fn () => $this->actingAs($this->admin->fresh())->get(route('admin.entries.index', ['contentType' => 'page']));

    expect(SharedSlots::item($list(), AdminSlot::EntriesRowActions, 'easy.edit-on-page'))->toBeNull();

    AdminPreferences::of($this->admin)->setSimpleMode(true);
    $item = SharedSlots::item($list(), AdminSlot::EntriesRowActions, 'easy.edit-on-page');

    expect($item['label'])->toBe('Edit on the page')
        ->and($item['props']['href'])->toBe('/admin/easy/pages/__entry__')
        ->and(SharedSlots::item($this->actingAs($this->admin->fresh())->get(route('admin.entries.index', ['contentType' => 'post'])), AdminSlot::EntriesRowActions, 'easy.edit-on-page'))->toBeNull();
});
