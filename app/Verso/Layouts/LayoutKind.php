<?php

namespace App\Verso\Layouts;

use App\Verso\Core\Enums\Capability;

// What a theme builder layout replaces: the content of a page (template), the header, the footer, or nothing (popup).
enum LayoutKind: string
{
    case Template = 'template';
    case Header = 'header';
    case Footer = 'footer';
    case Popup = 'popup';

    // Plain name for people.
    public function label(): string
    {
        return match ($this) {
            self::Template => __('Template'),
            self::Header => __('Header'),
            self::Footer => __('Footer'),
            self::Popup => __('Popup or banner'),
        };
    }

    // Capability needed to build and change layouts of this kind.
    public function capability(): Capability
    {
        return $this === self::Popup ? Capability::EditPopups : Capability::EditTemplates;
    }
}
