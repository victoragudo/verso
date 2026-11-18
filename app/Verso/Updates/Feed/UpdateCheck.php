<?php

namespace App\Verso\Updates\Feed;

use App\Verso\Core\Support\Version;
use App\Verso\Updates\Enums\UpdateChannel;

// The result of an update check: the running version and the newest release for the channel.
final readonly class UpdateCheck
{
    public function __construct(
        public string $currentVersion,
        public UpdateChannel $channel,
        public ?Release $latest,
        public ?string $checkedAt,
        public ?string $error = null,
    ) {}

    // True when the newest release is newer than the running version.
    public function updateAvailable(): bool
    {
        $current = Version::tryParse($this->currentVersion);

        return $this->latest !== null && $current !== null && $this->latest->semver()->isNewerThan($current);
    }

    // The release to install, or null when the site is up to date.
    public function availableRelease(): ?Release
    {
        return $this->updateAvailable() ? $this->latest : null;
    }
}
