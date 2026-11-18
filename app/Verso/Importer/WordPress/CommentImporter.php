<?php

namespace App\Verso\Importer\WordPress;

use App\Verso\Content\Models\Entry;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Support\WordPressDates;
use App\Verso\Importer\Wxr\WxrComment;
use App\Verso\Importer\Wxr\WxrItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Copies approved and waiting comments when the comments table exists. Spam, trash and pingbacks are skipped.
class CommentImporter
{
    protected const TABLE = 'comments';

    /** @var list<string>|null */
    protected ?array $columns = null;

    public function __construct(protected WordPressDates $dates) {}

    // True when the Comments module has created its table.
    public function available(): bool
    {
        return $this->columns() !== [];
    }

    public function import(Entry $entry, WxrItem $item, ImportState $state): void
    {
        if ($item->comments === []) {
            return;
        }

        if (! $this->available() || $this->entryColumns($entry) === []) {
            $state->report->add('comments', ImportReport::SKIPPED, count($item->comments));

            return;
        }

        /** @var array<int, int> $localIds WordPress comment id => comment id */
        $localIds = [];

        foreach ($item->comments as $comment) {
            if (! $comment->isWanted()) {
                $state->report->add('comments', ImportReport::SKIPPED);

                continue;
            }

            $localIds[$comment->id] = $this->store($entry, $comment, $state, $localIds);
        }
    }

    /** @param array<int, int> $localIds */
    protected function store(Entry $entry, WxrComment $comment, ImportState $state, array $localIds): int
    {
        $date = $this->dates->parse($comment->dateGmt, $comment->date) ?? now()->toImmutable();
        $owner = $this->entryColumns($entry);

        $existing = DB::table(self::TABLE)
            ->where($owner)
            ->where('created_at', $date->toDateTimeString())
            ->when($this->has('author_name'), fn ($query) => $query->where('author_name', $comment->authorName))
            ->value('id');

        if ($existing !== null) {
            $state->report->add('comments', ImportReport::SKIPPED);

            return (int) $existing;
        }

        $row = array_intersect_key([
            ...$owner,
            'user_id' => $state->userIdForWordPressId($comment->userId),
            'parent_id' => $localIds[$comment->parentId] ?? null,
            'author_name' => $comment->authorName,
            'author_email' => $comment->authorEmail !== '' ? $comment->authorEmail : null,
            'author_url' => $comment->authorUrl !== '' ? $comment->authorUrl : null,
            'author_ip' => $comment->authorIp !== '' ? $comment->authorIp : null,
            'content' => $comment->content,
            'body' => $comment->content,
            'status' => $comment->isApproved() ? 'approved' : 'pending',
            'meta' => json_encode(['wordpress' => ['id' => (string) $comment->id]]),
            'created_at' => $date->toDateTimeString(),
            'updated_at' => $date->toDateTimeString(),
        ], array_flip($this->columns()));

        $state->report->add('comments', ImportReport::CREATED);

        return (int) DB::table(self::TABLE)->insertGetId($row);
    }

    /**
     * The columns that link a comment to its entry: "entry_id" or a "commentable" morph.
     *
     * @return array<string, int|string>
     */
    protected function entryColumns(Entry $entry): array
    {
        if ($this->has('entry_id')) {
            return ['entry_id' => $entry->id];
        }

        if ($this->has('commentable_id') && $this->has('commentable_type')) {
            return ['commentable_type' => $entry->getMorphClass(), 'commentable_id' => $entry->id];
        }

        return [];
    }

    protected function has(string $column): bool
    {
        return in_array($column, $this->columns(), true);
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return $this->columns ??= Schema::hasTable(self::TABLE) ? Schema::getColumnListing(self::TABLE) : [];
    }
}
