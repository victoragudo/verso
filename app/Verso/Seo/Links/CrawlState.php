<?php

namespace App\Verso\Seo\Links;

use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;

// Progress of the link check, kept in the settings so every server and worker sees the same state.
class CrawlState
{
    public const KEY = 'seo.link_check';

    public const IDLE = 'idle';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public function __construct(
        protected Settings $settings,
        protected int $staleAfter,
    ) {}

    /**
     * @return array{status: string, started_at: string|null, finished_at: string|null, updated_at: string|null, total: int, checked: int}
     */
    public function current(): array
    {
        $value = $this->settings->get(self::KEY);
        $value = is_array($value) ? $value : [];

        return [
            'status' => in_array($value['status'] ?? null, [self::RUNNING, self::DONE], true) ? (string) $value['status'] : self::IDLE,
            'started_at' => is_string($value['started_at'] ?? null) ? $value['started_at'] : null,
            'finished_at' => is_string($value['finished_at'] ?? null) ? $value['finished_at'] : null,
            'updated_at' => is_string($value['updated_at'] ?? null) ? $value['updated_at'] : null,
            'total' => (int) ($value['total'] ?? 0),
            'checked' => (int) ($value['checked'] ?? 0),
        ];
    }

    public function isRunning(): bool
    {
        $state = $this->current();

        if ($state['status'] !== self::RUNNING) {
            return false;
        }

        // A check whose worker died long ago may be started again.
        $updated = $state['updated_at'] ?? $state['started_at'];

        return $updated !== null && Carbon::parse($updated)->gt(Carbon::now()->subSeconds(max(60, $this->staleAfter)));
    }

    // True once a check finished, so link based issues can be shown.
    public function hasResults(): bool
    {
        return $this->current()['finished_at'] !== null;
    }

    // Starts a new check. Returns false when one is already running.
    public function start(int $total): bool
    {
        if ($this->isRunning()) {
            return false;
        }

        $now = Carbon::now()->toIso8601String();
        $this->save([...$this->current(), 'status' => self::RUNNING, 'started_at' => $now, 'updated_at' => $now, 'total' => $total, 'checked' => 0]);

        return true;
    }

    public function advance(int $checked): void
    {
        $state = $this->current();
        $this->save([...$state, 'checked' => $state['checked'] + $checked, 'updated_at' => Carbon::now()->toIso8601String()]);
    }

    public function finish(): void
    {
        $now = Carbon::now()->toIso8601String();
        $this->save([...$this->current(), 'status' => self::DONE, 'finished_at' => $now, 'updated_at' => $now]);
    }

    /** @param array<string, mixed> $state */
    protected function save(array $state): void
    {
        $this->settings->set(self::KEY, $state);
    }
}
