<?php

namespace App\Verso\Access\Permissions;

use App\Models\User;
use App\Verso\Access\Enums\EntryAction;
use App\Verso\Content\ContentTypeRegistry;

// Entry capabilities: the one for every content type, or the one for a single type.
class EntryPermissions
{
    public function __construct(protected ContentTypeRegistry $types) {}

    // With no type, true when the user may do this for at least one content type.
    public function allows(User $user, EntryAction $action, ?string $type): bool
    {
        if ($user->hasCapability($action->legacy())) {
            return true;
        }

        if ($type !== null) {
            return $user->hasCapability($action->capabilityFor($type));
        }

        foreach ($this->types->names() as $name) {
            if ($user->hasCapability($action->capabilityFor($name))) {
                return true;
            }
        }

        return false;
    }

    // May open the list of this type: some way to write or edit its entries.
    public function canAccess(User $user, ?string $type): bool
    {
        return $this->canCreate($user, $type)
            || $this->allows($user, EntryAction::EditOwn, $type)
            || $this->allows($user, EntryAction::EditOthers, $type);
    }

    public function canCreate(User $user, ?string $type): bool
    {
        return $this->allows($user, EntryAction::Create, $type) || $this->allows($user, EntryAction::EditOthers, $type);
    }

    // May see drafts, scheduled and trashed entries of other people in the list.
    public function canReadPrivate(User $user, string $type): bool
    {
        return $this->allows($user, EntryAction::ReadPrivate, $type) || $this->allows($user, EntryAction::EditOthers, $type);
    }
}
