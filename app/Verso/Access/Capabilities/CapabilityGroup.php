<?php

namespace App\Verso\Access\Capabilities;

// The groups of the capability list, in the same order as the admin menu. Plugins add their own group.
final class CapabilityGroup
{
    public const CONTENT = 'Content';

    public const DESIGN = 'Design';

    public const TOOLS = 'Tools';

    public const ADMINISTRATION = 'Administration';

    public const SETTINGS = 'Settings';

    // Core groups first, then plugin groups by name.
    public static function order(string $group): int
    {
        return match ($group) {
            self::CONTENT => 10,
            self::DESIGN => 20,
            self::TOOLS => 30,
            self::ADMINISTRATION => 40,
            self::SETTINGS => 50,
            default => 60,
        };
    }
}
