<?php

namespace App\Verso\Importer\Support;

// Counts what an import created, updated, skipped or failed, plus a list of warnings.
class ImportReport
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    /** @var list<string> */
    public const KINDS = ['authors', 'terms', 'media', 'entries', 'comments', 'redirects'];

    /** @var array<string, array<string, int>> */
    protected array $counts = [];

    /** @var list<string> */
    protected array $messages = [];

    protected int $droppedMessages = 0;

    public function __construct(protected int $messageLimit = 200) {}

    public function add(string $kind, string $outcome, int $amount = 1): void
    {
        $this->counts[$kind][$outcome] = ($this->counts[$kind][$outcome] ?? 0) + $amount;
    }

    public function count(string $kind, string $outcome): int
    {
        return $this->counts[$kind][$outcome] ?? 0;
    }

    // Keep a warning for the user. After the limit, warnings are only counted.
    public function warn(string $message): void
    {
        if (count($this->messages) >= $this->messageLimit) {
            $this->droppedMessages++;

            return;
        }

        $this->messages[] = $message;
    }

    /** @return list<string> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function hasFailures(): bool
    {
        foreach ($this->counts as $outcomes) {
            if (($outcomes[self::FAILED] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * One row per kind, for tables in the console and the admin page.
     *
     * @return list<array{kind: string, created: int, updated: int, skipped: int, failed: int}>
     */
    public function rows(): array
    {
        return array_map(fn (string $kind): array => [
            'kind' => $kind,
            'created' => $this->count($kind, self::CREATED),
            'updated' => $this->count($kind, self::UPDATED),
            'skipped' => $this->count($kind, self::SKIPPED),
            'failed' => $this->count($kind, self::FAILED),
        ], self::KINDS);
    }

    /**
     * @return array{rows: list<array{kind: string, created: int, updated: int, skipped: int, failed: int}>, messages: list<string>, dropped_messages: int}
     */
    public function toArray(): array
    {
        return [
            'rows' => $this->rows(),
            'messages' => $this->messages,
            'dropped_messages' => $this->droppedMessages,
        ];
    }
}
