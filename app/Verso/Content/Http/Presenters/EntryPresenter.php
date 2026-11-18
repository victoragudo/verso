<?php

namespace App\Verso\Content\Http\Presenters;

use App\Models\User;
use App\Verso\Content\Admin\EntryListColumns;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Plugins\Filters\AdminEntryRowFilter;

// Turns entries into plain arrays for the admin pages.
class EntryPresenter
{
    public function __construct(
        protected TaxonomyRegistry $taxonomies,
        protected EntryListColumns $columns,
    ) {}

    /**
     * One row of the entries list. Plugins can add data with the AdminEntryRowFilter.
     *
     * @return array<string, mixed>
     */
    public function row(Entry $entry, User $user): array
    {
        return AdminEntryRowFilter::apply([
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'status' => $entry->status->value,
            'author' => $entry->author === null ? null : ['id' => $entry->author->id, 'name' => $entry->author->name],
            'published_at' => $entry->published_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
            'url' => $entry->url(),
            'columns' => $this->columns->values($entry),
            'can' => [
                'update' => ! $entry->trashed() && $user->can('update', $entry),
                'delete' => ! $entry->trashed() && $user->can('delete', $entry),
                'restore' => $entry->trashed() && $user->can('restore', $entry),
            ],
        ], $entry, $user);
    }

    /**
     * Everything the editor needs.
     *
     * @return array<string, mixed>
     */
    public function full(Entry $entry): array
    {
        $terms = [];
        foreach ($this->taxonomies->forContentType($entry->type) as $name => $taxonomy) {
            $terms[$name] = $entry->termsIn($name)->map(fn (Term $term): int => $term->id)->values()->all();
        }

        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'status' => $entry->status->value,
            'blocks' => $entry->blocks ?? [],
            'excerpt' => $entry->excerpt,
            'author' => $entry->author === null ? null : ['id' => $entry->author->id, 'name' => $entry->author->name],
            'parent_id' => $entry->parent_id,
            'menu_order' => $entry->menu_order,
            'published_at' => $entry->published_at?->toIso8601String(),
            'meta' => (object) ($entry->meta ?? []),
            'url' => $entry->url(),
            'created_at' => $entry->created_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
            'deleted_at' => $entry->deleted_at?->toIso8601String(),
            'terms' => (object) $terms,
        ];
    }

    /**
     * @return array{id: int, title: string, author: string|null, created_at: string|null}
     */
    public function revision(Revision $revision): array
    {
        return [
            'id' => $revision->id,
            'title' => $revision->title,
            'author' => $revision->user?->name,
            'created_at' => $revision->created_at?->toIso8601String(),
        ];
    }
}
