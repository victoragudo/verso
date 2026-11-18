<?php

use App\Models\User;
use App\Verso\Access\Actions\CloneRole;
use App\Verso\Access\Actions\DeleteRole;
use App\Verso\Access\Actions\ResetRole;
use App\Verso\Access\Actions\SaveRole;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\RoleRegistry;
use App\Verso\Access\Roles\Roles;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Inês Carvalho']);
});

it('lists the built-in roles first with their default capabilities', function () {
    $roles = app(Roles::class)->all();

    expect(array_keys($roles))->toBe(['admin', 'editor', 'author', 'contributor', 'subscriber'])
        ->and($roles['admin']->locked)->toBeTrue()
        ->and($roles['admin']->name)->toBe('Administrator')
        ->and($roles['editor']->builtIn)->toBeTrue()
        ->and($roles['editor']->capabilities)->toContain('edit_any_entry', 'moderate_comments')
        ->and($roles['subscriber']->capabilities)->toBe([]);
});

it('shows the shop manager only when a plugin turns it on', function () {
    expect(app(Roles::class)->exists('shop_manager'))->toBeFalse();

    app(OptionalRoles::class)->enable(Role::ShopManager);
    app(Roles::class)->flush();

    expect(app(Roles::class)->find('shop_manager')->source)->toBe('plugin:shop');
});

it('adds custom roles after the built-in roles, sorted by name', function () {
    customRole('seo_manager', ['manage_permalinks'], 'SEO manager');
    customRole('archivist', ['read_private_entries'], 'Archivist');

    expect(array_slice(array_keys(app(Roles::class)->all()), -2))->toBe(['archivist', 'seo_manager'])
        ->and(app(Roles::class)->find('seo_manager')->builtIn)->toBeFalse();
});

it('drops saved capabilities that no longer exist', function () {
    customRole('bookings_desk', ['bookings.manage', 'upload_files']);

    expect(app(Roles::class)->find('bookings_desk')->capabilities)->toBe(['upload_files']);
});

it('stores only the changes of a built-in role, so new defaults still arrive', function () {
    $editor = app(Roles::class)->find('editor');
    $capabilities = [...array_diff($editor->capabilities, ['moderate_comments']), 'manage_menus'];

    $saved = app(SaveRole::class)->handle($this->admin, $editor, ['name' => 'Editor', 'description' => $editor->description, 'capabilities' => array_values($capabilities)]);
    $row = StoredRole::query()->where('slug', 'editor')->firstOrFail();

    expect($saved->modified)->toBeTrue()
        ->and($saved->capabilities)->toContain('manage_menus')->not->toContain('moderate_comments')
        ->and($row->capabilities)->toBe(['manage_menus'])
        ->and($row->removed_capabilities)->toBe(['moderate_comments']);
});

it('forgets the stored row when a built-in role goes back to its defaults', function () {
    $author = app(Roles::class)->find('author');
    app(SaveRole::class)->handle($this->admin, $author, ['name' => 'Writer', 'capabilities' => $author->capabilities]);

    expect(app(Roles::class)->find('author')->name)->toBe('Writer');

    app(SaveRole::class)->handle($this->admin, app(Roles::class)->find('author'), ['name' => 'Author', 'description' => $author->description, 'capabilities' => $author->capabilities]);

    expect(StoredRole::query()->where('slug', 'author')->exists())->toBeFalse();
});

it('resets a built-in role', function () {
    $author = app(Roles::class)->find('author');
    app(SaveRole::class)->handle($this->admin, $author, ['name' => 'Author', 'capabilities' => ['edit_own_entries']]);

    $reset = app(ResetRole::class)->handle($this->admin, app(Roles::class)->find('author'));

    expect($reset->modified)->toBeFalse()
        ->and($reset->capabilities)->toContain('publish_entries', 'upload_files');
});

it('never changes the administrator role', function () {
    app(SaveRole::class)->handle($this->admin, app(Roles::class)->find('admin'), ['name' => 'Boss', 'capabilities' => []]);
})->throws(ValidationException::class);

it('clones any role into a custom role with a free slug', function () {
    $first = app(CloneRole::class)->handle($this->admin, app(Roles::class)->find('editor'));
    $second = app(CloneRole::class)->handle($this->admin, app(Roles::class)->find('editor'));
    $named = app(CloneRole::class)->handle($this->admin, app(Roles::class)->find('author'), 'Guest writer');

    expect($first->slug)->toBe('editor_copy')
        ->and($first->name)->toBe('Editor (copy)')
        ->and($first->basedOn)->toBe('editor')
        ->and($first->capabilities)->toEqualCanonicalizing(app(Roles::class)->find('editor')->capabilities)
        ->and($second->slug)->toBe('editor_copy_2')
        ->and($named->slug)->toBe('guest_writer');
});

it('makes a slug that never clashes with a built-in role', function () {
    expect(app(CloneRole::class)->freeSlug('Editor'))->toBe('editor_2')
        ->and(app(CloneRole::class)->freeSlug('2024 interns'))->toBe('role_2024_interns');
});

it('deletes a custom role and takes it away from users', function () {
    customRole('night_desk', ['moderate_comments'], 'Night desk');
    $user = giveExtraRoles(User::factory()->author()->create(), 'night_desk');

    $users = app(DeleteRole::class)->handle($this->admin, app(Roles::class)->find('night_desk'));

    expect($users)->toBe(1)
        ->and(DB::table('role_user')->count())->toBe(0)
        ->and(app(Roles::class)->exists('night_desk'))->toBeFalse()
        ->and($user->fresh()->roleSlugs())->toBe(['author']);
});

it('never deletes a built-in role', function () {
    app(DeleteRole::class)->handle($this->admin, app(Roles::class)->find('editor'));
})->throws(ValidationException::class);

it('lets plugins add roles that cannot be deleted', function () {
    app(RoleRegistry::class)->register(new RoleDefinition('box_office', 'Box office', 'Sells tickets.', ['upload_files'], 'plugin:tickets', builtIn: true));
    app(Roles::class)->flush();

    expect(app(Roles::class)->find('box_office')->builtIn)->toBeTrue()
        ->and(app(Roles::class)->defaults('box_office')->capabilities)->toBe(['upload_files']);
});

it('refuses plugin roles with a built-in slug', function () {
    app(RoleRegistry::class)->register(new RoleDefinition('editor', 'Editor', source: 'plugin:tickets'));
})->throws(InvalidArgumentException::class);

it('keeps working before the roles table exists', function () {
    Schema::drop('roles');
    app(Roles::class)->flush();

    expect(app(Roles::class)->slugs())->toContain('admin', 'editor');
});
