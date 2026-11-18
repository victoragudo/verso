<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Core\Enums\Capability;

// Maps Verso classes and calls a plugin uses to the capability it should declare in verso.json.
final class CapabilityMap
{
    // Exact classes and the capability their use needs.
    protected const CLASSES = [
        'App\\Verso\\Content\\Actions\\PublishEntry' => Capability::PublishEntries,
        'App\\Verso\\Content\\Actions\\ScheduleEntry' => Capability::PublishEntries,
        'App\\Verso\\Content\\Actions\\TrashEntry' => Capability::DeleteEntries,
        'App\\Verso\\Content\\Actions\\CreateTerm' => Capability::ManageTerms,
        'App\\Verso\\Content\\Actions\\UpdateTerm' => Capability::ManageTerms,
        'App\\Verso\\Content\\Actions\\DeleteTerm' => Capability::ManageTerms,
        'App\\Verso\\Content\\Actions\\CreateEntry' => Capability::EditAnyEntry,
        'App\\Verso\\Content\\Actions\\UpdateEntry' => Capability::EditAnyEntry,
        'App\\Verso\\Content\\Actions\\RestoreEntry' => Capability::EditAnyEntry,
        'App\\Verso\\Content\\Actions\\RestoreRevision' => Capability::EditAnyEntry,
        'App\\Verso\\Content\\Actions\\ApplyEntryStatus' => Capability::EditAnyEntry,
        'App\\Verso\\Content\\Actions\\SyncEntryTerms' => Capability::EditAnyEntry,
    ];

    // Namespaces whose classes all need the same capability.
    protected const NAMESPACES = [
        'App\\Verso\\Media\\Actions\\' => Capability::UploadFiles,
        'App\\Verso\\Comments\\Actions\\' => Capability::ModerateComments,
        'App\\Verso\\Users\\Actions\\' => Capability::ManageUsers,
        'App\\Verso\\Site\\Actions\\' => Capability::ManageSettings,
        'App\\Verso\\Plugins\\Actions\\' => Capability::ManagePlugins,
        'App\\Verso\\Extensions\\' => Capability::ManagePlugins,
        'App\\Verso\\Theme\\Actions\\' => Capability::ManageThemes,
        'App\\Verso\\Importer\\' => Capability::ImportContent,
    ];

    // Models whose static write methods need a capability.
    protected const MODELS = [
        'App\\Models\\User' => Capability::ManageUsers,
        'App\\Verso\\Content\\Models\\Entry' => Capability::EditAnyEntry,
        'App\\Verso\\Content\\Models\\Term' => Capability::ManageTerms,
        'App\\Verso\\Media\\Models\\Media' => Capability::UploadFiles,
        'App\\Verso\\Comments\\Models\\Comment' => Capability::ModerateComments,
        'App\\Verso\\Core\\Models\\Setting' => Capability::ManageSettings,
    ];

    // Static model methods that write rows.
    protected const WRITE_METHODS = ['create', 'forcecreate', 'insert', 'updateorcreate', 'firstorcreate', 'upsert', 'destroy', 'truncate'];

    // Class that stores settings. Calling set() on it needs manage_settings.
    public const SETTINGS_CLASS = 'App\\Verso\\Core\\Settings\\Settings';

    // The capability a reference to this class needs, or null.
    public function forClass(string $class): ?Capability
    {
        $class = ltrim($class, '\\');

        if (isset(self::CLASSES[$class])) {
            return self::CLASSES[$class];
        }

        foreach (self::NAMESPACES as $namespace => $capability) {
            if (str_starts_with($class, $namespace)) {
                return $capability;
            }
        }

        return null;
    }

    // The capability a static write call on a model needs, like User::create(), or null.
    public function forModelWrite(string $class, string $method): ?Capability
    {
        $capability = self::MODELS[ltrim($class, '\\')] ?? null;

        return $capability !== null && in_array(strtolower($method), self::WRITE_METHODS, true) ? $capability : null;
    }
}
