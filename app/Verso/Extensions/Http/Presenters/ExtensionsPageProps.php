<?php

namespace App\Verso\Extensions\Http\Presenters;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Extensions\Registry\RegistryClient;
use App\Verso\Extensions\Registry\RegistryEntry;
use App\Verso\Extensions\Verification\VerificationSettings;

// Data of the "Add new" and "Browse" tabs of the Plugins and themes page.
class ExtensionsPageProps
{
    public function __construct(
        protected RegistryClient $registry,
        protected ExtensionInstaller $installer,
        protected VerificationSettings $verification,
    ) {}

    /**
     * The kinds of package the user may add.
     *
     * @return list<ExtensionKind>
     */
    public function allowedKinds(?User $user): array
    {
        return array_values(array_filter(
            ExtensionKind::cases(),
            fn (ExtensionKind $kind): bool => (bool) $user?->can($kind->capability()->value),
        ));
    }

    /**
     * Upload form data and plugin check settings.
     *
     * @return array{kinds: list<string>, maxUploadKb: int, verification: array{blockHighRisk: bool, canChange: bool, sandbox: bool}}
     */
    public function add(?User $user): array
    {
        return [
            'kinds' => array_map(fn (ExtensionKind $kind): string => $kind->value, $this->allowedKinds($user)),
            'maxUploadKb' => (int) config('verso.extensions.max_upload_kb'),
            'verification' => [
                'blockHighRisk' => $this->verification->blocksHighRisk(),
                'canChange' => (bool) $user?->can(Capability::ManageSettings->value),
                'sandbox' => (bool) config('verso.extensions.verification.sandbox.enabled'),
            ],
        ];
    }

    /**
     * Registry packages the user may install, with the installed version of each.
     *
     * @return array{configured: bool, error: string|null, entries: list<array<string, mixed>>}
     */
    public function browse(?User $user): array
    {
        $kinds = $this->allowedKinds($user);

        try {
            $entries = array_values(array_filter($this->registry->entries(), fn (RegistryEntry $entry): bool => in_array($entry->kind, $kinds, true)));
            $error = null;
        } catch (ExtensionInstallFailed $exception) {
            $entries = [];
            $error = $exception->getMessage();
        }

        return [
            'configured' => $this->registry->isConfigured(),
            'error' => $error,
            'entries' => array_map(fn (RegistryEntry $entry): array => [
                ...$entry->toArray(),
                'installedVersion' => $this->installer->installedVersion($entry->kind, $entry->slug),
            ], $entries),
        ];
    }
}
