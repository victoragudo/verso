<?php

namespace App\Verso\Importer\WordPress;

use App\Models\User;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Models\Redirect;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Wxr\WxrAuthor;
use App\Verso\Importer\Wxr\WxrItem;
use App\Verso\Importer\Wxr\WxrReader;
use App\Verso\Importer\Wxr\WxrTerm;
use Closure;
use InvalidArgumentException;
use Throwable;

// Imports a whole WordPress export file. Safe to run again: nothing is copied twice.
class WordPressImporter
{
    public function __construct(
        protected AuthorImporter $authors,
        protected TermImporter $terms,
        protected AttachmentImporter $attachments,
        protected EntryImporter $entries,
        protected CommentImporter $comments,
        protected RedirectWriter $redirects,
        protected UpdateEntry $updateEntry,
        protected int $messageLimit = 200,
    ) {}

    // How many items the progress callback will report.
    public function countItems(string $path): int
    {
        return (new WxrReader($path))->countItems();
    }

    /**
     * Run the import. The callback gets the number of items done after each item.
     *
     * @param  (Closure(int, ImportReport): void)|null  $onProgress
     */
    public function import(string $path, User $fallbackAuthor, ?Closure $onProgress = null): ImportReport
    {
        $reader = new WxrReader($path);
        $state = new ImportState($fallbackAuthor, new ImportReport($this->messageLimit), $reader->channel());

        $this->entries->loadPreviousImports($state);

        // First pass: authors, terms and files, so posts can point to them.
        foreach ($reader->records() as $record) {
            if ($record instanceof WxrAuthor) {
                $this->authors->import($record, $state);
            } elseif ($record instanceof WxrTerm) {
                $this->terms->import($record, $state);
            } elseif ($record->isAttachment()) {
                $this->step($state, $onProgress, 'media', fn () => $this->attachments->import($record, $state));
            }
        }

        $this->terms->resolveParents($state);

        // Second pass: posts, pages and their comments.
        foreach ($reader->records() as $record) {
            if ($record instanceof WxrItem && ! $record->isAttachment()) {
                $this->step($state, $onProgress, 'entries', fn () => $this->item($record, $state));
            }
        }

        $this->resolveParents($state);

        foreach ($state->imported as $imported) {
            $this->redirects->write($imported, $state);
        }

        if (! $this->comments->available()) {
            $state->report->warn(__('Comments were not imported because the comments table does not exist yet.'));
        }

        return $state->report;
    }

    protected function item(WxrItem $item, ImportState $state): void
    {
        if ($this->entries->shouldSkip($item)) {
            $state->report->add('entries', ImportReport::SKIPPED);

            return;
        }

        $entry = $this->entries->import($item, $state);

        if ($entry !== null) {
            $this->comments->import($entry, $item, $state);
        }
    }

    // Run one item. One broken item is reported and does not stop the import.
    /** @param (Closure(int, ImportReport): void)|null $onProgress */
    protected function step(ImportState $state, ?Closure $onProgress, string $kind, Closure $work): void
    {
        try {
            $work();
        } catch (Throwable $exception) {
            $state->report->add($kind, ImportReport::FAILED);
            $state->report->warn(__('An item could not be imported: :error', ['error' => $exception->getMessage()]));
            report($exception);
        }

        $state->processed++;

        if ($onProgress !== null) {
            $onProgress($state->processed, $state->report);
        }
    }

    // Link pages to parents that came later in the file.
    protected function resolveParents(ImportState $state): void
    {
        foreach ($state->pendingParents as $entryId => $wordpressParentId) {
            $entry = Entry::query()->find($entryId);
            $parentId = $state->entries[$wordpressParentId] ?? null;

            if ($entry === null) {
                continue;
            }

            if ($parentId === null) {
                $state->report->warn(__('The parent of ":title" was not in the file, so it stays at the top level.', ['title' => $entry->title]));

                continue;
            }

            try {
                $pathBefore = $entry->path();
                $this->updateEntry->handle($entry, ['parent_id' => $parentId]);
                $this->forgetTemporaryRedirect($pathBefore, $entry);
            } catch (InvalidArgumentException) {
                $state->report->warn(__('":title" cannot have that parent, so it stays at the top level.', ['title' => $entry->title]));
            }
        }

        $state->pendingParents = [];
    }

    // The path before the parent was set only existed during this import, so no one needs a redirect from it.
    protected function forgetTemporaryRedirect(string $pathBefore, Entry $entry): void
    {
        Redirect::query()->where('from_path', $pathBefore)->where('to_path', $entry->path())->delete();
    }
}
