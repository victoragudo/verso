<?php

namespace App\Verso\Extensions\Actions;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Packages\PackageExtractor;
use App\Verso\Extensions\StagedPackage;
use App\Verso\Extensions\Verification\PluginVerifier;
use App\Verso\Extensions\Verification\ThemeVerifier;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

// Unpacks a staged plugin or theme into a temporary folder, verifies it and stores the report for the consent screen.
class VerifyStagedPackage
{
    public function __construct(
        protected PluginVerifier $plugins,
        protected ThemeVerifier $themes,
        protected PackageExtractor $extractor,
        protected Filesystem $files,
    ) {}

    // The stored report of the staged package, verified now when there is none for this exact zip.
    public function handle(StagedPackage $staged, ?int $userId = null): PluginVerification
    {
        $existing = PluginVerification::forStaged($staged->token, $staged->sha256);

        if ($existing !== null) {
            return $existing;
        }

        $kind = $staged->package->kind;
        $work = rtrim((string) config('verso.extensions.storage_path'), '/\\').DIRECTORY_SEPARATOR.'verify'.DIRECTORY_SEPARATOR.Str::uuid();
        $folder = $work.DIRECTORY_SEPARATOR.$staged->package->slug;

        try {
            $this->extractor->extract($staged->zipPath, $staged->package, $folder);
            $report = $kind === ExtensionKind::Theme ? $this->themes->verify($folder) : $this->plugins->verify($folder);
        } finally {
            $this->files->deleteDirectory($work);
        }

        return PluginVerification::record($report, $staged->source, $staged->sha256, $staged->token, $userId, $kind);
    }
}
