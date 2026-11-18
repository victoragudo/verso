<?php

use App\Verso\Access\Capabilities\CapabilityDefinition;
use App\Verso\Access\Capabilities\CapabilityGroup;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Enums\RiskLevel;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Enums\Capability;

it('declares every core capability with a label, group and risk', function () {
    $registry = app(CapabilityRegistry::class);

    foreach (Capability::cases() as $capability) {
        $definition = $registry->get($capability->value);

        expect($definition)->not->toBeNull()
            ->and($definition->label)->not->toBe('')
            ->and($definition->description)->not->toBe('')
            ->and(CapabilityGroup::order($definition->group))->toBeLessThan(60);
    }
});

it('keeps the old capability names', function () {
    $keys = app(CapabilityRegistry::class)->keys();

    expect($keys)->toContain('manage_settings', 'manage_users', 'edit_any_entry', 'edit_own_entries', 'publish_entries', 'delete_entries', 'upload_files', 'manage_terms', 'moderate_comments', 'manage_plugins', 'manage_themes', 'import_content', 'ai.use', 'shop.manage', 'manage_updates', 'view_admin_bar', 'manage_system');
});

it('adds seven capabilities for every content type, right after the ones for all types', function () {
    $keys = app(CapabilityRegistry::class)->keys();
    $start = array_search('read_private_entries', $keys, true);

    expect(array_slice($keys, $start + 1, 7))->toBe([
        'entries.post.create', 'entries.post.edit_own', 'entries.post.edit_others', 'entries.post.publish',
        'entries.post.delete_own', 'entries.post.delete_others', 'entries.post.read_private',
    ])->and($keys)->toContain('entries.page.publish');

    $definition = app(CapabilityRegistry::class)->get('entries.page.delete_others');

    expect($definition->label)->toBe("Delete others' pages")
        ->and($definition->section)->toBe('Pages')
        ->and($definition->risk)->toBe(RiskLevel::High);
});

it('knows capabilities of content types added later', function () {
    app(ContentTypeRegistry::class)->register(new ContentType('recipe', 'Recipe', 'Recipes'));

    expect(app(CapabilityRegistry::class)->has('entries.recipe.publish'))->toBeTrue()
        ->and(app(CapabilityRegistry::class)->has('entries.cocktail.publish'))->toBeFalse();
});

it('sorts capabilities by group in menu order', function () {
    $groups = array_values(array_unique(array_map(fn (CapabilityDefinition $definition): string => $definition->group, app(CapabilityRegistry::class)->all())));

    expect(array_slice($groups, 0, 5))->toBe(['Content', 'Design', 'Tools', 'Administration', 'Settings']);
});

it('follows implied capabilities all the way down', function () {
    $registry = app(CapabilityRegistry::class);

    expect($registry->expand('manage_users'))->toContain('manage_users', 'list_users', 'create_users', 'edit_users', 'promote_users', 'delete_users')
        ->and($registry->expand('manage_settings'))->toContain('manage_permalinks', 'manage_fields')
        ->and($registry->expand('manage_themes'))->toContain('edit_site', 'manage_menus')
        ->and($registry->expand('upload_files'))->toBe(['upload_files']);
});

it('lets plugins add capabilities and default roles', function () {
    $registry = app(CapabilityRegistry::class);
    $registry->register(new CapabilityDefinition('bookings.manage', 'Manage bookings', 'Accept and cancel table bookings.', 'Bookings', RiskLevel::Medium, 'plugin:bookings', roles: ['editor']));

    expect($registry->get('bookings.manage')->group)->toBe('Bookings')
        ->and($registry->defaultsFor('editor'))->toContain('bookings.manage')
        ->and($registry->defaultsFor('author'))->not->toContain('bookings.manage');

    $registry->forgetSource('plugin:bookings');

    expect($registry->has('bookings.manage'))->toBeFalse();
});

it('refuses keys that clash or are not valid', function (string $key, string $source) {
    app(CapabilityRegistry::class)->register(new CapabilityDefinition($key, 'Something', source: $source));
})->with([
    'core key from a plugin' => ['manage_users', 'plugin:bookings'],
    'content type pattern' => ['entries.post.publish', 'plugin:bookings'],
    'upper case' => ['Bookings.Manage', 'plugin:bookings'],
    'spaces' => ['bookings manage', 'plugin:bookings'],
])->throws(InvalidArgumentException::class);
