<?php

namespace App\Verso\Content\Actions;

use App\Models\User;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Support\SlugGenerator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a new entry of any content type.
 *
 * @phpstan-type EntryData array{
 *     title?: string,
 *     slug?: string|null,
 *     status?: EntryStatus|string|null,
 *     blocks?: list<array<string, mixed>>|null,
 *     excerpt?: string|null,
 *     parent_id?: int|null,
 *     menu_order?: int|null,
 *     published_at?: CarbonInterface|string|null,
 *     meta?: array<string, mixed>|null,
 *     terms?: array<string, list<int|string>|null>,
 * }
 */
class CreateEntry
{
    public function __construct(
        protected ContentTypeRegistry $types,
        protected SlugGenerator $slugs,
        protected ApplyEntryStatus $applyStatus,
        protected SyncEntryTerms $syncTerms,
    ) {}

    /**
     * @param  EntryData  $data
     */
    public function handle(User $author, string $type, array $data): Entry
    {
        $this->types->get($type);

        return DB::transaction(function () use ($author, $type, $data): Entry {
            $entry = new Entry([
                'type' => $type,
                'title' => $data['title'] ?? '',
                'blocks' => $data['blocks'] ?? [],
                'excerpt' => $data['excerpt'] ?? null,
                'menu_order' => $data['menu_order'] ?? 0,
                'meta' => $data['meta'] ?? [],
            ]);
            $entry->author()->associate($author);

            $parent = isset($data['parent_id']) ? Entry::query()->find($data['parent_id']) : null;

            if (! $entry->canHaveParent($parent)) {
                throw new InvalidArgumentException('This entry cannot have that parent.');
            }

            $entry->parent_id = $parent?->id;
            $entry->slug = $this->slugs->forEntry($type, ($data['slug'] ?? '') ?: $entry->title, $entry->parent_id);
            $entry->save();

            $publishedNow = $this->applyStatus->handle(
                $entry,
                self::status($data['status'] ?? null),
                self::date($data['published_at'] ?? null),
            );

            $this->syncTerms->handle($entry, $data['terms'] ?? []);

            EntrySaved::dispatch($entry);

            if ($publishedNow) {
                EntryPublished::dispatch($entry);
            }

            return $entry;
        });
    }

    public static function status(EntryStatus|string|null $status): EntryStatus
    {
        if ($status instanceof EntryStatus) {
            return $status;
        }

        return EntryStatus::tryFrom((string) $status) ?? EntryStatus::Draft;
    }

    public static function date(CarbonInterface|string|null $date): ?CarbonInterface
    {
        if ($date === null || $date === '') {
            return null;
        }

        return $date instanceof CarbonInterface ? $date : Carbon::parse($date);
    }
}
