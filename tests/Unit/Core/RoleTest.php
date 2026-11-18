<?php

use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;

it('gives every capability to admins', function () {
    expect(Role::Admin->capabilities())->toBe(Capability::cases());
});

it('gives subscribers no capabilities', function () {
    expect(Role::Subscriber->capabilities())->toBe([]);
});

it('lets editors edit any entry but not manage settings', function () {
    expect(Role::Editor->has(Capability::EditAnyEntry))->toBeTrue()
        ->and(Role::Editor->has(Capability::ManageTerms))->toBeTrue()
        ->and(Role::Editor->has(Capability::ManageSettings))->toBeFalse()
        ->and(Role::Editor->has(Capability::ManagePlugins))->toBeFalse();
});

it('lets authors publish only their own entries', function () {
    expect(Role::Author->has(Capability::EditOwnEntries))->toBeTrue()
        ->and(Role::Author->has(Capability::PublishEntries))->toBeTrue()
        ->and(Role::Author->has(Capability::EditAnyEntry))->toBeFalse()
        ->and(Role::Author->has(Capability::DeleteEntries))->toBeFalse();
});

it('lets contributors write drafts but not publish', function () {
    expect(Role::Contributor->capabilities())->toBe([Capability::EditOwnEntries, Capability::ReadPrivateEntries])
        ->and(Role::Contributor->has(Capability::PublishEntries))->toBeFalse();
});

it('has a readable label', function (Role $role, string $label) {
    expect($role->label())->toBe($label);
})->with([
    [Role::Admin, 'Administrator'],
    [Role::Editor, 'Editor'],
    [Role::Contributor, 'Contributor'],
]);

it('finds capabilities from ability names', function () {
    expect(Capability::fromAbility('publish_entries'))->toBe(Capability::PublishEntries)
        ->and(Capability::fromAbility('update'))->toBeNull();
});

it('lets admins and editors use the AI assistant but nobody else', function (Role $role, bool $allowed) {
    expect($role->has(Capability::UseAi))->toBe($allowed);
})->with([
    [Role::Admin, true],
    [Role::Editor, true],
    [Role::Author, false],
    [Role::Contributor, false],
    [Role::Subscriber, false],
]);

it('maps the ai.use ability to its capability', function () {
    expect(Capability::fromAbility('ai.use'))->toBe(Capability::UseAi);
});

it('lets editors see and fix SEO issues, but not authors', function () {
    expect(Role::Editor->has(Capability::ViewSeo))->toBeTrue()
        ->and(Role::Editor->has(Capability::ManageSeo))->toBeTrue()
        ->and(Role::Author->has(Capability::ViewSeo))->toBeFalse()
        ->and(Capability::ViewSeo->value)->toBe('seo.view')
        ->and(Capability::ManageSeo->label())->toBe('Fix SEO issues, check links and turn 404s into redirects');
});
