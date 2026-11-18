<?php

namespace App\Verso\Fields\Enums;

// Where a field group shows in the entry editor.
enum FieldPosition: string
{
    case Main = 'main';
    case Sidebar = 'sidebar';

    public function label(): string
    {
        return match ($this) {
            self::Main => __('Main column'),
            self::Sidebar => __('Sidebar'),
        };
    }
}
