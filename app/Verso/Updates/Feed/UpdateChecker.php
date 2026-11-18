<?php

namespace App\Verso\Updates\Feed;

use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Support\UpdateSettings;
use Illuminate\Contracts\Cache\Repository as Cache;

// Asks the release feed for new versions and keeps the answer in the cache.
class UpdateChecker
{
    public function __construct(
        protected ReleaseFeed $feed,
        protected Cache $cache,
        protected UpdateSettings $settings,
        protected string $currentVersion,
        protected string $cacheKey,
        protected int $cacheTtl,
    ) {}

    public function isConfigured(): bool
    {
        return $this->feed->isConfigured();
    }

    public function currentVersion(): string
    {
        return $this->currentVersion;
    }

    // The last stored check. It never calls the network, so pages stay fast.
    public function status(): UpdateCheck
    {
        $stored = $this->stored();

        return $this->build($stored['releases'], $stored['checked_at'], $stored['error']);
    }

    // Read the feed now and store the answer. A failure keeps the releases found before.
    public function check(): UpdateCheck
    {
        if (! $this->feed->isConfigured()) {
            return $this->build([], null, null);
        }

        $stored = $this->stored();

        try {
            $releases = $this->feed->releases();
            $error = null;
        } catch (UpdateFailed $exception) {
            $releases = $stored['releases'];
            $error = $exception->getMessage();
        }

        $checkedAt = now()->toIso8601String();

        $this->cache->put($this->cacheKey, [
            'releases' => array_map(fn (Release $release): array => $release->toArray(), $releases),
            'checked_at' => $checkedAt,
            'error' => $error,
        ], $this->cacheTtl);

        return $this->build($releases, $checkedAt, $error);
    }

    public function forget(): void
    {
        $this->cache->forget($this->cacheKey);
    }

    /**
     * @return array{releases: list<Release>, checked_at: string|null, error: string|null}
     */
    protected function stored(): array
    {
        $data = $this->cache->get($this->cacheKey);
        $data = is_array($data) ? $data : [];
        $releases = [];

        foreach ((array) ($data['releases'] ?? []) as $item) {
            $release = is_array($item) ? Release::fromArray($item) : null;

            if ($release !== null) {
                $releases[] = $release;
            }
        }

        return [
            'releases' => $releases,
            'checked_at' => is_string($data['checked_at'] ?? null) ? $data['checked_at'] : null,
            'error' => is_string($data['error'] ?? null) ? $data['error'] : null,
        ];
    }

    /** @param list<Release> $releases */
    protected function build(array $releases, ?string $checkedAt, ?string $error): UpdateCheck
    {
        $channel = $this->settings->channel();

        return new UpdateCheck(
            currentVersion: $this->currentVersion,
            channel: $channel,
            latest: $this->feed->latestFor($releases, $channel),
            checkedAt: $checkedAt,
            error: $error,
        );
    }
}
