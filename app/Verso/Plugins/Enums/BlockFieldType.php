<?php

namespace App\Verso\Plugins\Enums;

// Kinds of inputs the editor can show for a plugin block attr.
enum BlockFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    // A CSS size like "1.5rem" or "clamp(1rem, 2vw, 2rem)", edited with the length input.
    case Length = 'length';
    case Url = 'url';
    case Toggle = 'toggle';
    case Select = 'select';
    // A picture from the media library, stored as {"id": 12, "url": "...", "alt": "..."}.
    case Media = 'media';
    // An icon name from the bundled icon set (App\Verso\Editor\Icons\IconLibrary).
    case Icon = 'icon';
    // A hex color like #1f6f5c or a theme color token like var:color.accent.
    case Color = 'color';
    // A date and time in the site time zone, like 2026-12-24T18:00.
    case DateTime = 'datetime';
}
