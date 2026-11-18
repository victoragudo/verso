<?php

namespace App\Verso\Easy\Pages;

use App\Verso\Content\Models\Entry;
use App\Verso\I18n\Translate;

// The earlier versions of a page for "Restore this page": when, who and a short summary, newest first.
class PageVersions
{
    /**
     * @return list<array{id: int, savedAt: string|null, author: string|null, title: string, summary: string, blocks: list<array<string, mixed>>}>
     */
    public function forEntry(Entry $entry, int $limit): array
    {
        $revisions = $entry->revisions()->with('user:id,name')->limit(max(1, $limit))->get();
        $versions = [];

        foreach ($revisions as $revision) {
            $versions[] = [
                'id' => $revision->id,
                'savedAt' => $revision->created_at?->toIso8601String(),
                'author' => $revision->user?->name,
                'title' => $revision->title,
                'summary' => $this->summary($revision->blocks ?? []),
                'blocks' => $revision->blocks ?? [],
            ];
        }

        return $versions;
    }

    /**
     * A plain summary like "3 sections" or "Empty page".
     *
     * @param  array<mixed>  $blocks
     */
    public function summary(array $blocks): string
    {
        return Translate::icu('{count, plural, =0 {Empty page} one {# section} other {# sections}}', ['count' => count($blocks)]);
    }
}
