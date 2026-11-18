<?php

namespace App\Verso\Importer\WordPress;

use App\Models\User;
use App\Verso\Importer\Content\MediaMap;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\Wxr\WxrChannel;

// What one import run has learned so far: who is who, and which WordPress ids became which Verso rows.
class ImportState
{
    public MediaMap $media;

    /** @var array<string, int> WordPress login => user id */
    protected array $authorsByLogin = [];

    /** @var array<int, int> WordPress user id => user id */
    protected array $authorsById = [];

    /** @var array<int, User> */
    protected array $users = [];

    /** @var array<int, int> WordPress post id => entry id */
    public array $entries = [];

    /** @var array<string, int> "taxonomy:slug" => term id */
    public array $terms = [];

    /** @var list<array{term: int, taxonomy: string, parent: string}> */
    public array $pendingTermParents = [];

    /** @var array<int, int> entry id => WordPress parent post id */
    public array $pendingParents = [];

    /** @var list<ImportedEntry> */
    public array $imported = [];

    public int $processed = 0;

    public function __construct(
        public readonly User $fallbackAuthor,
        public readonly ImportReport $report,
        public WxrChannel $channel = new WxrChannel,
    ) {
        $this->media = new MediaMap;
        $this->users[$fallbackAuthor->id] = $fallbackAuthor;
    }

    public function rememberAuthor(string $login, int $wordpressId, User $user): void
    {
        $this->users[$user->id] = $user;

        if ($login !== '') {
            $this->authorsByLogin[strtolower($login)] = $user->id;
        }

        if ($wordpressId > 0) {
            $this->authorsById[$wordpressId] = $user->id;
        }
    }

    // The author for a WordPress login, or the person who runs the import.
    public function authorFor(string $login): User
    {
        $id = $this->authorsByLogin[strtolower($login)] ?? null;

        return $id === null ? $this->fallbackAuthor : $this->users[$id];
    }

    public function userIdForWordPressId(int $wordpressId): ?int
    {
        return $this->authorsById[$wordpressId] ?? null;
    }
}
