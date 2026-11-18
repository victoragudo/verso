<?php

namespace App\Verso\Shortcodes\Enums;

// Where a text with shortcodes comes from. Places other than Content must be turned on in config.
enum ShortcodePlace: string
{
    case Content = 'content';
    case Excerpt = 'excerpt';
    case CustomField = 'custom_field';
    case ThemeSetting = 'theme_setting';
    case FormEmail = 'form_email';
    case Footer = 'footer';
    case Blade = 'blade';

    // Content and Blade always run. Other places are opt-in.
    public function enabled(): bool
    {
        if ($this === self::Content || $this === self::Blade) {
            return true;
        }

        return in_array($this->value, (array) config('verso.shortcodes.places'), true);
    }
}
