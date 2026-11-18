<?php

namespace App\Verso\Content\Actions;

use App\Models\User;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Content\Support\SlugGenerator;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes an entry. Saves the old content as a revision and keeps only the newest revisions.
 *
 * @phpstan-import-type EntryData from CreateEntry
 */
class UpdateEntry
{
    /** @var list<string> */
    protected const CONTENT_FIELDS = ['title', 'blocks', 'excerpt', 'meta'];

    public function __construct(
        protected SlugGenerator $slugs,
        protected ApplyEntryStatus $applyStatus,
        protected SyncEntryTerms $syncTerms,
        protected Settings $settings,
    ) {}

    /**
     * @param  EntryData  $data
     */
    public function handle(Entry $entry, array $data, ?User $editor = null): Entry
    {
        return DB::transaction(function () use ($entry, $data, $editor): Entry {
            $oldPath = $entry->isPublished() ? $entry->path() : null;
            $before = $entry->snapshot();

            $entry->fill(array_intersect_key($data, array_flip([...self::CONTENT_FIELDS, 'menu_order'])));

            $parentChanged = $this->applyParent($entry, $data);
            $this->applySlug($entry, $data, $parentChanged);

            if ($entry->isDirty(self::CONTENT_FIELDS)) {
                Revision::capture($entry, $editor, $before);
            }

            $entry->save();

            $publishedNow = false;

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $publishedNow = $this->applyStatus->handle(
                    $entry,
                    CreateEntry::status($data['status']),
                    CreateEntry::date($data['published_at'] ?? null),
                );
            }

            if (array_key_exists('terms', $data)) {
                $this->syncTerms->handle($entry, $data['terms']);
            }

            $entry->pruneRevisions((int) $this->settings->get('content.revisions_limit'));
            $this->redirectOldPath($entry, $oldPath);

            EntrySaved::dispatch($entry);

            if ($publishedNow) {
                EntryPublished::dispatch($entry);
            }

            return $entry;
        });
    }

    /**
     * @param  EntryData  $data
     */
    protected function applyParent(Entry $entry, array $data): bool
    {
        if (! array_key_exists('parent_id', $data) || $data['parent_id'] === $entry->parent_id) {
            return false;
        }

        $parent = $data['parent_id'] === null ? null : Entry::query()->find($data['parent_id']);

        if (! $entry->canHaveParent($parent)) {
            throw new InvalidArgumentException('This entry cannot have that parent.');
        }

        $entry->parent()->associate($parent);

        return true;
    }

    // Rebuild the slug when it was edited or the parent changed.
    /** @param EntryData $data */
    protected function applySlug(Entry $entry, array $data, bool $parentChanged): void
    {
        $wanted = array_key_exists('slug', $data) ? (string) $data['slug'] : $entry->slug;

        if ($wanted === $entry->slug && ! $parentChanged) {
            return;
        }

        $entry->slug = $this->slugs->forEntry($entry->type, $wanted ?: $entry->title, $entry->parent_id, $entry->id);
    }

    // Old links keep working after a published entry moves.
    protected function redirectOldPath(Entry $entry, ?string $oldPath): void
    {
        $entry->unsetRelation('parent');

        if ($oldPath === null || ! $entry->isPublished() || $entry->path() === $oldPath) {
            return;
        }

        Redirect::point($oldPath, $entry->path());
    }
}
