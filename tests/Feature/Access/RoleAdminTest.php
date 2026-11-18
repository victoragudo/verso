<?php

use App\Models\User;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\System\Models\ConfigChange;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Beatriz Lopes']);
});

it('shows the matrix of roles and capabilities', function () {
    User::factory()->editor()->count(2)->create();
    customRole('seo_manager', ['manage_permalinks'], 'SEO manager');

    $this->actingAs($this->admin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/access/roles/index')
            ->has('roles', 6)
            ->where('roles.0.slug', 'admin')
            ->where('roles.0.locked', true)
            ->where('roles.0.users', 1)
            ->where('roles.1.slug', 'editor')
            ->where('roles.1.users', 2)
            ->where('roles.5.slug', 'seo_manager')
            ->where('roles.5.builtIn', false)
            ->has('capabilities.0', fn (Assert $capability) => $capability
                ->where('key', 'edit_own_entries')
                ->where('group', 'Content')
                ->where('section', 'All content types')
                ->where('risk', 'low')
                ->etc())
            ->has('grantable')
            ->has('audit'));
});

it('keeps the roles pages away from people without manage_roles', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.roles.show', 'author'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.roles.store'), ['name' => 'Night desk'])->assertForbidden();
    $this->actingAs($editor)->put(route('admin.roles.matrix'), ['roles' => ['author' => []]])->assertForbidden();
});

it('creates a custom role, starting from another role', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), ['name' => 'Guest writer', 'description' => 'Writes the weekly column.', 'from' => 'author'])
        ->assertRedirect(route('admin.roles.show', 'guest_writer'));

    $role = app(Roles::class)->find('guest_writer');

    expect($role->name)->toBe('Guest writer')
        ->and($role->basedOn)->toBe('author')
        ->and($role->capabilities)->toEqualCanonicalizing(app(Roles::class)->find('author')->capabilities)
        ->and(ConfigChange::query()->where('action', AccessAudit::ROLE_CREATE)->where('key', 'guest_writer')->exists())->toBeTrue();
});

it('makes a readable free slug from the name', function () {
    customRole('seo_manager', [], 'SEO manager');

    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), ['name' => 'SEO manager', 'capabilities' => ['manage_permalinks']])
        ->assertRedirect(route('admin.roles.show', 'seo_manager_2'));

    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), ['name' => 'Ação social'])
        ->assertRedirect(route('admin.roles.show', 'acao_social'));
});

it('refuses a slug that another role uses and unknown capabilities', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), ['name' => 'Editor', 'slug' => 'editor', 'capabilities' => ['rule_the_world']])
        ->assertSessionHasErrors(['slug', 'capabilities.0']);
});

it('saves changed roles from the matrix and skips the administrator', function () {
    $author = app(Roles::class)->find('author');

    $this->actingAs($this->admin)
        ->put(route('admin.roles.matrix'), ['roles' => [
            'author' => [...$author->capabilities, 'moderate_comments'],
            'contributor' => app(Roles::class)->find('contributor')->capabilities,
            'admin' => [],
        ]])
        ->assertRedirect();

    expect(app(Roles::class)->find('author')->capabilities)->toContain('moderate_comments')
        ->and(StoredRole::query()->pluck('slug')->all())->toBe(['author'])
        ->and(app(Roles::class)->find('admin')->locked)->toBeTrue();
});

it('shows one role with its members, defaults and change log', function () {
    $writer = giveExtraRoles(User::factory()->author()->create(['name' => 'Joana Reis']), 'editor');
    User::factory()->editor()->create(['name' => 'Luís Faria']);
    $this->actingAs($this->admin)->put(route('admin.roles.update', 'editor'), ['name' => 'Section editor', 'capabilities' => ['edit_any_entry']]);

    $this->actingAs($this->admin)
        ->get(route('admin.roles.show', 'editor'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/access/roles/show')
            ->where('role.name', 'Section editor')
            ->where('role.modified', true)
            ->where('role.capabilities', ['edit_any_entry'])
            ->where('defaults', fn ($defaults) => in_array('moderate_comments', $defaults->all(), true))
            ->where('members.total', 2)
            ->where('members.items.0.name', 'Joana Reis')
            ->where('members.items.0.main', false)
            ->where('members.items.1.main', true)
            ->where('audit.0.action', AccessAudit::ROLE_UPDATE)
            ->where('audit.0.user', 'Beatriz Lopes')
            ->where('audit.0.details.renamed', 'Editor')
            ->where('canViewAs', true));
});

it('gives a 404 for a role that does not exist', function () {
    $this->actingAs($this->admin)->get(route('admin.roles.show', 'pirate_captain'))->assertNotFound();
});

it('clones, resets and deletes roles', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.clone', 'editor'), ['name' => 'Copy desk'])
        ->assertRedirect(route('admin.roles.show', 'copy_desk'));

    $this->actingAs($this->admin)->put(route('admin.roles.update', 'author'), ['name' => 'Author', 'capabilities' => ['edit_own_entries']]);
    $this->actingAs($this->admin)->post(route('admin.roles.reset', 'author'))->assertRedirect();
    expect(app(Roles::class)->find('author')->modified)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', 'copy_desk'))->assertRedirect(route('admin.roles.index'));
    expect(app(Roles::class)->exists('copy_desk'))->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', 'editor'))->assertSessionHasErrors('role');
    expect(app(Roles::class)->exists('editor'))->toBeTrue();
});

it('compares roles side by side', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.roles.compare', ['roles' => 'editor,author,pirate_captain']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/access/roles/compare')
            ->where('selected', ['editor', 'author'])
            ->where('limit', 4)
            ->has('capabilities')
            ->has('roles', 5));
});

it('compares editor and author when nothing is picked', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.roles.compare'))
        ->assertInertia(fn (Assert $page) => $page->where('selected', ['editor', 'author']));
});

it('keeps role changes out of the System settings log', function () {
    $this->actingAs($this->admin)->put(route('admin.roles.update', 'author'), ['name' => 'Columnist']);

    $this->actingAs($this->admin)
        ->get(route('admin.system.settings.index'))
        ->assertInertia(fn (Assert $page) => $page->where('audit', []));
});
