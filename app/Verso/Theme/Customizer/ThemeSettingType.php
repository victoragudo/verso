<?php

namespace App\Verso\Theme\Customizer;

// The kinds of fields a theme can declare in the "settings" part of theme.json.
enum ThemeSettingType: string
{
    case Color = 'color';
    case Font = 'font';
    case Media = 'media';
    // A number with a fixed unit and a range. Values may also use other CSS units since sizes take any unit.
    case Size = 'size';
    // Any CSS size: every unit, keywords and custom values like clamp(). Range and units are optional.
    case Length = 'length';
    case Text = 'text';
    case Toggle = 'toggle';
}
