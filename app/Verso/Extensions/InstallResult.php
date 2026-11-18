<?php

namespace App\Verso\Extensions;

use App\Verso\Extensions\Enums\ExtensionKind;

// What an install or update did.
final readonly class InstallResult
{
    public function __construct(
        public ExtensionKind $kind,
        public string $slug,
        public string $name,
        public string $version,
        public ?string $previousVersion,
        public bool $activated,
    ) {}

    public function isUpdate(): bool
    {
        return $this->previousVersion !== null;
    }

    // A short sentence for the admin toast.
    public function message(): string
    {
        $replace = ['name' => $this->name, 'version' => $this->version];
        $isPlugin = $this->kind === ExtensionKind::Plugin;

        return match (true) {
            $this->isUpdate() && ! $this->activated => __(':name updated to :version.', $replace),
            $this->isUpdate() && $isPlugin => __(':name updated to :version and enabled.', $replace),
            $this->isUpdate() => __(':name updated to :version and active.', $replace),
            ! $this->activated => __(':name :version installed.', $replace),
            $isPlugin => __(':name :version installed and enabled.', $replace),
            default => __(':name :version installed and active.', $replace),
        };
    }
}
