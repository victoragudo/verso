<?php

use App\Verso\Core\Enums\Capability;

it('turns snake case values into readable labels', function () {
    expect(Capability::ManageSettings->label())->toBe('Manage settings')
        ->and(Capability::ModerateComments->label())->toBe('Moderate comments')
        ->and(Capability::ImportContent->label())->toBe('Import content');
});

it('gives the AI capability a readable label instead of its dotted value', function () {
    expect(Capability::UseAi->value)->toBe('ai.use')
        ->and(Capability::UseAi->label())->toBe('Use the AI assistant');
});

it('gives the shop capability a readable label instead of its dotted value', function () {
    expect(Capability::ManageShop->label())->toBe('Manage the shop');
});

it('lists a label for every capability, keyed by value', function () {
    $labels = Capability::labels();

    expect(array_keys($labels))->toBe(array_column(Capability::cases(), 'value'))
        ->and($labels['upload_files'])->toBe('Upload files')
        ->and($labels['ai.use'])->toBe('Use the AI assistant');
});

it('never shows a raw value with a dot or an underscore', function () {
    foreach (Capability::labels() as $label) {
        expect($label)->not->toContain('.')->not->toContain('_');
    }
});

it('finds a capability from its ability name', function () {
    expect(Capability::fromAbility('ai.use'))->toBe(Capability::UseAi)
        ->and(Capability::fromAbility('bake_bread'))->toBeNull();
});
