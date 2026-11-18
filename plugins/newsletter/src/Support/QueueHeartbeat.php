<?php

namespace Verso\Plugins\Newsletter\Support;

use App\Verso\Core\Settings\Settings;
use Carbon\CarbonImmutable;
use Throwable;

// Remembers when a queue worker last ran a job, so the admin can be told when campaigns would get stuck.
class QueueHeartbeat
{
    // Setting that holds the time of the last heartbeat (ISO 8601, UTC).
    public const SETTING = 'newsletter.queue_heartbeat';

    public function __construct(
        protected Settings $settings,
        protected int $staleMinutes,
    ) {}

    public function record(?CarbonImmutable $at = null): void
    {
        $this->settings->set(self::SETTING, ($at ?? CarbonImmutable::now())->utc()->toIso8601String());
    }

    public function lastSeen(): ?CarbonImmutable
    {
        $value = $this->settings->get(self::SETTING);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    // True when jobs run right away (the "sync" driver), so no worker is needed.
    public function usesSyncQueue(): bool
    {
        $connection = (string) config('queue.default');

        return (string) config("queue.connections.{$connection}.driver", $connection) === 'sync';
    }

    // True when no heartbeat was seen within the allowed time.
    public function isStale(?CarbonImmutable $now = null): bool
    {
        $lastSeen = $this->lastSeen();

        return $lastSeen === null || $lastSeen->lt(($now ?? CarbonImmutable::now())->subMinutes(max(1, $this->staleMinutes)));
    }

    /**
     * The admin notice, or null when the queue looks healthy.
     *
     * @return array{title: string, message: string, lastSeen: string|null}|null
     */
    public function notice(): ?array
    {
        if ($this->usesSyncQueue() || ! $this->isStale()) {
            return null;
        }

        return [
            'title' => __('newsletter::Campaigns will not be sent: no queue worker is running'),
            'message' => __('newsletter::The queue connection is ":connection", so emails wait for a worker. Run ":worker" and the scheduler (":scheduler" every minute), or set :setting.', [
                'connection' => (string) config('queue.default'),
                'worker' => 'php artisan queue:work',
                'scheduler' => 'php artisan schedule:run',
                'setting' => 'QUEUE_CONNECTION=sync',
            ]),
            'lastSeen' => $this->lastSeen()?->toIso8601String(),
        ];
    }
}
