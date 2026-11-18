<?php

namespace App\Verso\Easy\Actions;

use App\Models\User;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Drafts\PageDrafts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

// Saves the blocks of the visual page editor through UpdateEntry, so revisions, events and caches work as in the entry editor.
class SaveEasyPage
{
    public function __construct(
        protected UpdateEntry $update,
        protected PageDrafts $drafts,
    ) {}

    /**
     * Keeps the status of the entry, or publishes it when asked. Publishing needs the publish right,
     * and an empty page is only published when the person said yes. Saving clears the automatic draft.
     *
     * @param  list<array<string, mixed>>  $blocks
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Entry $entry, User $editor, array $blocks, bool $publish = false, bool $confirmEmpty = false): Entry
    {
        if ($publish && ! $editor->can('publish', $entry)) {
            throw new AuthorizationException(__('You cannot publish this page.'));
        }

        if ($publish && $blocks === [] && ! $confirmEmpty) {
            throw ValidationException::withMessages([
                'blocks' => __('This page is empty. Add a section first, or say yes to publish an empty page.'),
            ]);
        }

        $data = ['blocks' => $blocks];

        if ($publish && ! $entry->isPublished()) {
            $data['status'] = EntryStatus::Published->value;
            $data['published_at'] = null;
        }

        $entry = $this->update->handle($entry, $data, $editor);
        $this->drafts->clear($entry, $editor);

        return $entry;
    }
}
