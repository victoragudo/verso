<?php

use App\Models\User;
use App\Verso\Access\Actions\SaveRole;
use App\Verso\Access\Capabilities\CapabilityDefinition;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Enums\RiskLevel;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\RoleRegistry;
use App\Verso\Access\Roles\Roles;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    app('translator')->addLines([
        '*.harbor-bookings::Manage bookings' => 'Gestionar reservas',
        '*.harbor-bookings::Front desk' => 'Recepción',
        '*.Content' => 'Contenido',
        '*.Media' => 'Medios',
        '*.Upload and use media' => 'Subir y usar medios',
        '*.Delete own :items' => 'Borrar :items propias',
    ], 'es');
});

it('finds the plugin or theme slug of a capability from its source', function (string $source, ?string $plugin) {
    $definition = new CapabilityDefinition(key: 'harbor-bookings.manage', label: 'Manage bookings', source: $source);

    expect($definition->plugin())->toBe($plugin);
})->with([
    'plugin' => ['plugin:harbor-bookings', 'harbor-bookings'],
    'theme' => ['theme:ashgrove', 'ashgrove'],
    'core' => ['core', null],
    'custom' => ['custom', null],
]);

it('sends capability labels, groups and sections in the language of the viewer', function () {
    app()->setLocale('es');

    $plugin = new CapabilityDefinition(key: 'harbor-bookings.manage', label: 'Manage bookings', group: 'Harbor Bookings', risk: RiskLevel::High, source: 'plugin:harbor-bookings');
    $core = app(CapabilityRegistry::class)->get('upload_files');

    expect($plugin->present())->toMatchArray(['key' => 'harbor-bookings.manage', 'label' => 'Gestionar reservas', 'group' => 'Harbor Bookings', 'risk' => 'high'])
        ->and($plugin->toArray()['label'])->toBe('Manage bookings')
        ->and($core->present())->toMatchArray(['label' => 'Subir y usar medios', 'group' => 'Contenido', 'section' => 'Medios'])
        ->and(app(CapabilityRegistry::class)->get('entries.post.delete_own')->label)->toBe('Borrar posts propias');
});

it('translates built-in role names but never the names people typed', function () {
    app()->setLocale('es');

    $pluginRole = new RoleDefinition(slug: 'front_desk', name: 'Front desk', source: 'plugin:harbor-bookings', builtIn: true);
    $customRole = new RoleDefinition(slug: 'front_desk_team', name: 'Front desk', source: 'custom');

    expect($pluginRole->present()['name'])->toBe('Recepción')
        ->and($customRole->present()['name'])->toBe('Front desk');
});

it('keeps the English name of a plugin role when the translated name is sent back unchanged', function () {
    app()->setLocale('es');
    app(RoleRegistry::class)->register(new RoleDefinition(slug: 'front_desk', name: 'Front desk', capabilities: ['upload_files'], source: 'plugin:harbor-bookings', builtIn: true));
    app(Roles::class)->flush();
    $admin = User::factory()->admin()->create(['name' => 'Rosa Antunes']);
    $role = app(Roles::class)->find('front_desk');

    app(SaveRole::class)->handle($admin, $role, ['name' => 'Recepción', 'capabilities' => ['upload_files', 'moderate_comments']]);

    expect(StoredRole::query()->where('slug', 'front_desk')->value('name'))->toBe('Front desk');

    app(RoleRegistry::class)->forgetSource('plugin:harbor-bookings');
    app(Roles::class)->flush();
});
