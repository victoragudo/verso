<?php

namespace App\Verso\Easy\Drafts;

use App\Models\User;
use App\Verso\Content\Models\Entry;

// Keeps, finds and clears the automatic drafts of the visual page editor. A draft never touches the entry or its revisions.
class PageDrafts
{
    public function find(Entry $entry, User $user): ?PageDraft
    {
        return PageDraft::query()->where('entry_id', $entry->id)->where('user_id', $user->id)->first();
    }

    // The draft only when it is newer than the last save of the page, so an old draft never hides newer content.
    public function newerThanPage(Entry $entry, User $user): ?PageDraft
    {
        $draft = $this->find($entry, $user);

        if ($draft === null) {
            return null;
        }

        if ($entry->updated_at !== null && $draft->updated_at !== null && $draft->updated_at->lt($entry->updated_at)) {
            $draft->delete();

            return null;
        }

        return $draft;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function save(Entry $entry, User $user, array $blocks): PageDraft
    {
        $draft = $this->find($entry, $user) ?? new PageDraft;
        $draft->entry_id = $entry->id;
        $draft->user_id = $user->id;
        $draft->blocks = $blocks;
        $draft->updateTimestamps();
        $draft->save();

        return $draft;
    }

    public function clear(Entry $entry, User $user): void
    {
        PageDraft::query()->where('entry_id', $entry->id)->where('user_id', $user->id)->delete();
    }

    /**
     * What the editor gets: the blocks and the time of the draft.
     *
     * @return array{blocks: list<array<string, mixed>>, savedAt: string|null}
     */
    public function toArray(PageDraft $draft): array
    {
        return ['blocks' => $draft->blocks, 'savedAt' => $draft->updated_at?->toIso8601String()];
    }
}
