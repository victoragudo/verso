<?php

namespace App\Verso\Core\Admin;

// The sidebar section a nav item lives in. The order of the cases is the order in the sidebar.
enum NavGroup: string
{
    case Dashboard = 'dashboard';
    case Content = 'content';
    case Design = 'design';
    case Tools = 'tools';
    case Administration = 'administration';
    case Settings = 'settings';

    // The name people see above the section.
    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Content => 'Content',
            self::Design => 'Design',
            self::Tools => 'Tools',
            self::Administration => 'Administration',
            self::Settings => 'Settings',
        };
    }

    // Position of the section in the sidebar, starting at 0.
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
