<?php

namespace App\Verso\Extensions;

use App\Verso\Extensions\Packages\Package;
use Carbon\CarbonImmutable;

// An uploaded or downloaded package that waits for the admin to accept its permissions.
final readonly class StagedPackage
{
    public function __construct(
        public string $token,
        public Package $package,
        public string $zipPath,
        public string $source,
        public string $sha256,
        public CarbonImmutable $stagedAt,
        public ?string $installedVersion,
    ) {}

    // True when a version of this package is already installed.
    public function isUpdate(): bool
    {
        return $this->installedVersion !== null;
    }

    // True when the package is older than the installed version.
    public function isDowngrade(): bool
    {
        return $this->installedVersion !== null && version_compare($this->package->version, $this->installedVersion, '<');
    }

    /**
     * Data for the admin consent screen.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'package' => $this->package->toArray(),
            'source' => $this->source,
            'sha256' => $this->sha256,
            'installedVersion' => $this->installedVersion,
            'isUpdate' => $this->isUpdate(),
            'isDowngrade' => $this->isDowngrade(),
        ];
    }
}
