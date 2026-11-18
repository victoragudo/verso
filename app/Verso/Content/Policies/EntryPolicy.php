<?php

namespace App\Verso\Content\Policies;

use App\Models\User;
use App\Verso\Access\Enums\EntryAction;
use App\Verso\Access\Permissions\EntryPermissions;
use App\Verso\Content\ContentType;
use App\Verso\Content\Enums\EntryAbility;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters\EntryCapabilityFilter;

// Who can see, edit, publish and delete entries, per content type. Plugins can change each answer.
// A capability for every content type (like edit_any_entry) or one for the type (entries.post.edit_others) is enough.
class EntryPolicy
{
    public function __construct(protected EntryPermissions $permissions) {}

    // $type is a content type or its name. Routes pass the bound ContentType.
    public function viewAny(User $user, ContentType|string|null $type = null): bool
    {
        return EntryCapabilityFilter::apply($this->permissions->canAccess($user, $this->name($type)), $user, EntryAbility::ViewAny);
    }

    public function create(User $user, ContentType|string|null $type = null): bool
    {
        return EntryCapabilityFilter::apply($this->permissions->canCreate($user, $this->name($type)), $user, EntryAbility::Create);
    }

    // Users without the publish capability cannot change live entries.
    public function update(User $user, Entry $entry): bool
    {
        return EntryCapabilityFilter::apply($this->canUpdate($user, $entry), $user, EntryAbility::Update, $entry);
    }

    // $entry is the entry, or the content type name when there is no entry yet.
    public function publish(User $user, Entry|ContentType|string|null $entry = null): bool
    {
        $type = $entry instanceof Entry ? $entry->type : $this->name($entry);
        $allowed = $this->permissions->allows($user, EntryAction::Publish, $type)
            && (! $entry instanceof Entry || $this->update($user, $entry));

        return EntryCapabilityFilter::apply($allowed, $user, EntryAbility::Publish, $entry instanceof Entry ? $entry : null);
    }

    public function delete(User $user, Entry $entry): bool
    {
        return EntryCapabilityFilter::apply($this->canDelete($user, $entry), $user, EntryAbility::Delete, $entry);
    }

    public function restore(User $user, Entry $entry): bool
    {
        return EntryCapabilityFilter::apply($this->canDelete($user, $entry), $user, EntryAbility::Restore, $entry);
    }

    // May see this entry in the admin: own entries, published ones, or anything with "read private".
    public function view(User $user, Entry $entry): bool
    {
        return $this->viewAny($user, $entry->type)
            && ($entry->isOwnedBy($user) || $entry->isPublished() || $this->permissions->canReadPrivate($user, $entry->type));
    }

    protected function name(ContentType|string|null $type): ?string
    {
        return $type instanceof ContentType ? $type->name : $type;
    }

    protected function canUpdate(User $user, Entry $entry): bool
    {
        if ($this->permissions->allows($user, EntryAction::EditOthers, $entry->type)) {
            return true;
        }

        if (! $this->permissions->allows($user, EntryAction::EditOwn, $entry->type) || ! $entry->isOwnedBy($user)) {
            return false;
        }

        return $this->permissions->allows($user, EntryAction::Publish, $entry->type) || $entry->status->needsPublishCapability() === false;
    }

    protected function canDelete(User $user, Entry $entry): bool
    {
        if ($this->permissions->allows($user, EntryAction::DeleteOthers, $entry->type)) {
            return true;
        }

        return $this->permissions->allows($user, EntryAction::DeleteOwn, $entry->type) && $entry->isOwnedBy($user);
    }
}
