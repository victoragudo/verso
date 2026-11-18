<?php

namespace App\Verso\Extensions\Packages;

// The limits and file rules every uploaded package must follow.
final readonly class PackageRules
{
    /**
     * @param  list<string>  $allowedExtensions
     * @param  list<string>  $allowedPlainFiles
     */
    public function __construct(
        public int $maxUploadBytes,
        public int $maxUnpackedBytes,
        public int $maxFiles,
        public int $maxManifestBytes,
        public array $allowedExtensions,
        public array $allowedPlainFiles,
    ) {}

    // Build the rules from config/verso.php.
    public static function fromConfig(): self
    {
        return new self(
            maxUploadBytes: max(1, (int) config('verso.extensions.max_upload_kb')) * 1024,
            maxUnpackedBytes: max(1, (int) config('verso.extensions.max_unpacked_kb')) * 1024,
            maxFiles: max(1, (int) config('verso.extensions.max_files')),
            maxManifestBytes: max(1, (int) config('verso.extensions.max_manifest_kb')) * 1024,
            allowedExtensions: array_values(array_map(
                fn (mixed $extension): string => strtolower(ltrim(trim((string) $extension), '.')),
                (array) config('verso.extensions.allowed_extensions'),
            )),
            allowedPlainFiles: array_values(array_map(strval(...), (array) config('verso.extensions.allowed_plain_files'))),
        );
    }
}
