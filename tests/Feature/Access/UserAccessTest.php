<?php

use App\Models\User;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\System\Models\ConfigChange;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Nuno Teixeira']);
    $this->writer = User::factory()->author()->create(['name' => 'Rita Moura']);
});

it('shows the access page of a user', function () {
    customRole('newsletter_desk', ['upload_files']);
    giveExtraRoles($this->writer, 'newsletter_desk');
    overrideCapabilities($this->writer, grants: ['manage_menus'], denies: ['publish_entries']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.access.edit', $this->writer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/access/users/access')
            ->where('user.name', 'Rita Moura')
            ->where('user.role', 'author')
            ->where('user.extraRoles', ['newsletter_desk'])
            ->where('user.overrides', ['manage_menus' => true, 'publish_entries' => false])
            ->where('user.effective', fn ($effective) => in_array('manage_menus', $effective->all(), true) && ! in_array('publish_entries', $effective->all(), true))
            ->where('mainRoles', ['admin', 'editor', 'author', 'contributor', 'subscriber'])
            ->has('roles', 6)
            ->has('capabilities'));
});

it('saves roles, grants and denies and writes the audit log', function () {
    customRole('newsletter_desk', ['upload_files']);

    $this->actingAs($this->admin)
        ->put(route('admin.users.access.update', $this->writer), [
            'role' => 'contributor',
            'extra_roles' => ['newsletter_desk', 'contributor'],
            'grants' => ['manage_menus'],
            'denies' => ['upload_files'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $writer = $this->writer->fresh();

    expect($writer->roleSlugs())->toBe(['contributor', 'newsletter_desk'])
        ->and($writer->hasCapability('manage_menus'))->toBeTrue()
        ->and($writer->hasCapability('upload_files'))->toBeFalse()
        ->and(ConfigChange::query()->where('action', AccessAudit::USER_ROLE)->where('key', 'newsletter_desk')->value('new_value'))->toBe('added')
        ->and(ConfigChange::query()->where('action', AccessAudit::USER_ROLE)->where('key', 'author')->value('old_value'))->toBe('removed')
        ->and(ConfigChange::query()->where('action', AccessAudit::USER_ACCESS)->where('key', 'user:'.$writer->id)->count())->toBe(1);
});

it('refuses the same capability as grant and deny', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.access.update', $this->writer), ['role' => 'author', 'extra_roles' => [], 'grants' => ['manage_menus'], 'denies' => ['manage_menus']])
        ->assertSessionHasErrors('denies.0');
});

it('keeps the access page away from people who cannot promote users', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.users.access.edit', $this->writer))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.users.access.update', $this->writer), ['role' => 'editor', 'extra_roles' => [], 'grants' => [], 'denies' => []])->assertForbidden();
});

it('lets a promoter change people below them but not administrators', function () {
    $promoter = userWith(['promote_users', 'list_users', 'edit_own_entries', 'publish_entries', 'upload_files', 'delete_own_entries', 'read_private_entries']);

    $this->actingAs($promoter)->get(route('admin.users.access.edit', $this->writer))->assertOk();
    $this->actingAs($promoter)->get(route('admin.users.access.edit', $this->admin))->assertForbidden();
    $this->actingAs($promoter)
        ->put(route('admin.users.access.update', $this->writer), ['role' => 'editor', 'extra_roles' => [], 'grants' => [], 'denies' => []])
        ->assertSessionHasErrors('role');
});
