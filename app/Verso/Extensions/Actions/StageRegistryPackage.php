<?php

namespace App\Verso\Extensions\Actions;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Extensions\Registry\RegistryClient;
use App\Verso\Extensions\StagedPackage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

// Downloads a registry package, checks its sha256 sum and stages it for consent.
class StageRegistryPackage
{
    public function __construct(
        protected RegistryClient $registry,
        protected ExtensionInstaller $installer,
        protected Filesystem $files,
    ) {}

    public function handle(ExtensionKind $kind, string $slug): StagedPackage
    {
        $entry = $this->registry->find($kind, $slug)
            ?? throw ExtensionInstallFailed::because($kind === ExtensionKind::Plugin
                ? __('The plugin ":slug" is not in the registry.', ['slug' => $slug])
                : __('The theme ":slug" is not in the registry.', ['slug' => $slug]));

        $download = rtrim((string) config('verso.extensions.storage_path'), '/\\').DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.Str::uuid().'.zip';

        try {
            $this->registry->download($entry, $download);
            $staged = $this->installer->stage($download, $kind, 'registry');
        } finally {
            $this->files->delete($download);
        }

        if ($staged->package->slug !== $entry->slug || $staged->package->version !== $entry->version) {
            $this->installer->discard($staged->token);

            throw ExtensionInstallFailed::because(__('The downloaded package is :package, but the registry lists :listed.', ['package' => $staged->package->slug.' '.$staged->package->version, 'listed' => $entry->slug.' '.$entry->version]));
        }

        return $staged;
    }
}
