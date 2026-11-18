<?php

namespace App\Verso\Content\Admin;

use App\Models\User;
use App\Verso\Access\Enums\EntryAction;
use App\Verso\Access\Permissions\EntryPermissions;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use Illuminate\Database\Eloquent\Builder;

// The latest edited entries for the dashboard. Without "edit others" for a type, only own entries of it.
class RecentEntriesPanel
{
    public function __construct(
        protected ContentTypeRegistry $types,
        protected int $limit,
        protected EntryPermissions $permissions,
    ) {}

    /**
     * @return list<array{key: string, title: string, meta: string|null, href: string|null, status: string|null}>
     */
    public function __invoke(User $user): array
    {
        $types = array_values(array_filter($this->types->names(), fn (string $type): bool => $user->can('viewAny', [Entry::class, $type])));
        $everyone = array_values(array_filter($types, fn (string $type): bool => $this->permissions->allows($user, EntryAction::EditOthers, $type)));

        $labels = new ContentLabels($this->types);
        $entries = Entry::query()
            ->whereIn('type', $types)
            ->where('status', '!=', EntryStatus::Trash)
            ->where(fn (Builder $query) => $query->where('author_id', $user->id)->orWhereIn('type', $everyone))
            ->latest('updated_at')
            ->latest('id')
            ->limit($this->limit)
            ->get(['id', 'type', 'title', 'status', 'updated_at']);

        return array_values($entries->map(fn (Entry $entry): array => [
            'key' => 'entry-'.$entry->id,
            'title' => $entry->title === '' ? __('Untitled') : $entry->title,
            'meta' => __(':type, edited :time', [
                'type' => $labels->typeLabel($this->types->get($entry->type)),
                'time' => $entry->updated_at?->diffForHumans(),
            ]),
            'href' => route('admin.entries.edit', ['contentType' => $entry->type, 'entry' => $entry->id], false),
            'status' => $entry->status->value,
        ])->all());
    }
}
