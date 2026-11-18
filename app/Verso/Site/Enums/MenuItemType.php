<?php

namespace App\Verso\Site\Enums;

// What a menu item links to.
enum MenuItemType: string
{
    case Entry = 'entry';
    case Term = 'term';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Entry => __('Entry'),
            self::Term => __('Term'),
            self::Custom => __('Custom link'),
        };
    }
}
