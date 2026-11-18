<?php

namespace App\Verso\Access\Enums;

use App\Verso\Core\Enums\Capability;

// One thing a user can do with the entries of a content type. Each one is a capability per type.
enum EntryAction: string
{
    case Create = 'create';
    case EditOwn = 'edit_own';
    case EditOthers = 'edit_others';
    case Publish = 'publish';
    case DeleteOwn = 'delete_own';
    case DeleteOthers = 'delete_others';
    case ReadPrivate = 'read_private';

    // The capability for one content type, like "entries.post.publish".
    public function capabilityFor(string $type): string
    {
        return 'entries.'.$type.'.'.$this->value;
    }

    // The older capability that gives this action for every content type.
    public function legacy(): Capability
    {
        return match ($this) {
            self::Create, self::EditOwn => Capability::EditOwnEntries,
            self::EditOthers => Capability::EditAnyEntry,
            self::Publish => Capability::PublishEntries,
            self::DeleteOwn => Capability::DeleteOwnEntries,
            self::DeleteOthers => Capability::DeleteEntries,
            self::ReadPrivate => Capability::ReadPrivateEntries,
        };
    }

    public function label(string $items): string
    {
        return match ($this) {
            self::Create => __('Create :items', ['items' => $items]),
            self::EditOwn => __('Edit own :items', ['items' => $items]),
            self::EditOthers => __("Edit others' :items", ['items' => $items]),
            self::Publish => __('Publish :items', ['items' => $items]),
            self::DeleteOwn => __('Delete own :items', ['items' => $items]),
            self::DeleteOthers => __("Delete others' :items", ['items' => $items]),
            self::ReadPrivate => __('See unpublished :items of others', ['items' => $items]),
        };
    }

    public function risk(): RiskLevel
    {
        return match ($this) {
            self::Create, self::EditOwn, self::ReadPrivate => RiskLevel::Low,
            self::Publish, self::DeleteOwn => RiskLevel::Medium,
            self::EditOthers, self::DeleteOthers => RiskLevel::High,
        };
    }

    // Parse "entries.<type>.<action>" into the type and the action, or null.
    /** @return array{0: string, 1: self}|null */
    public static function parse(string $capability): ?array
    {
        if (preg_match('/^entries\.([a-z0-9_\-]+)\.([a-z_]+)$/', $capability, $match) !== 1) {
            return null;
        }

        $action = self::tryFrom($match[2]);

        return $action === null ? null : [$match[1], $action];
    }
}
