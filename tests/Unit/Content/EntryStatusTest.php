<?php

use App\Verso\Content\Enums\EntryStatus;

it('has labels', function () {
    expect(EntryStatus::Scheduled->label())->toBe('Scheduled');
});

it('knows which states need the publish capability', function () {
    expect(EntryStatus::Published->needsPublishCapability())->toBeTrue()
        ->and(EntryStatus::Scheduled->needsPublishCapability())->toBeTrue()
        ->and(EntryStatus::Draft->needsPublishCapability())->toBeFalse()
        ->and(EntryStatus::Trash->needsPublishCapability())->toBeFalse();
});

it('lists the states a user can pick', function () {
    expect(EntryStatus::editable())->toBe([EntryStatus::Draft, EntryStatus::Scheduled, EntryStatus::Published]);
});
