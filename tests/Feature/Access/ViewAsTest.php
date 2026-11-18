<?php

use App\Models\User;
use App\Verso\Access\Support\ViewAs;
use App\Verso\Core\Enums\Capability;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Carla Antunes']);
});

it('starts a preview and shows the admin as the role, with a banner', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roles.view-as', 'author'))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas(ViewAs::SESSION_KEY, 'author');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewAs', ['role' => 'author', 'name' => 'Author'])
            ->where('can.publish_entries', true)
            ->where('can.manage_users', false)
            ->where('versoNav', fn ($items) => ! collect($items)->contains('key', 'users') && collect($items)->contains('key', 'entries.post'))
            ->where('stats', fn ($stats) => ! collect($stats)->contains('key', 'users')));
});

it('hides admin pages the previewed role cannot open', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.view-as', 'author'));

    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.entries.index', 'post'))->assertOk();
});

it('is read only', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.view-as', 'editor'));

    $this->actingAs($this->admin)
        ->from(route('admin.entries.index', 'post'))
        ->post(route('admin.entries.store', 'post'), ['title' => 'Spring menu'])
        ->assertRedirect(route('admin.entries.index', 'post'))
        ->assertInertiaFlash('toast.type', 'error');

    $this->actingAs($this->admin)
        ->putJson(route('admin.list-views.update', 'comments'), ['columns' => []])
        ->assertForbidden();
});

it('exits the preview', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.view-as', 'author'));

    $this->actingAs($this->admin)
        ->delete(route('admin.view-as.stop'))
        ->assertRedirect(route('admin.roles.show', 'author'))
        ->assertSessionMissing(ViewAs::SESSION_KEY);

    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
});

it('is only for administrators and never for the administrator role', function () {
    $manager = userWith(['manage_roles']);

    $this->actingAs($manager)->post(route('admin.roles.view-as', 'author'))->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.roles.view-as', 'admin'))->assertForbidden();
});

it('drops a preview that is no longer allowed', function () {
    $this->actingAs($this->admin)
        ->withSession([ViewAs::SESSION_KEY => 'pirate_captain'])
        ->get(route('admin.dashboard'))
        ->assertSessionMissing(ViewAs::SESSION_KEY)
        ->assertInertia(fn (Assert $page) => $page->where('viewAs', null));

    $editor = User::factory()->editor()->create();
    $this->actingAs($editor)
        ->withSession([ViewAs::SESSION_KEY => 'author'])
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('viewAs', null)->where('can.'.Capability::EditAnyEntry->value, true));
});
