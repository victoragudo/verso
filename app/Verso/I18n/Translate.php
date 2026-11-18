<?php

namespace App\Verso\I18n;

// Short helpers for admin strings in PHP. text() is __(); icu() fills plurals and selects.
// A plugin passes its slug so "shop::Orders" is tried before the core "Orders".
class Translate
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function text(string $key, array $replace = [], ?string $plugin = null): string
    {
        if ($plugin !== null && $plugin !== '' && ! str_contains($key, '::')) {
            $namespaced = $plugin.'::'.$key;
            $translator = app('translator');

            // The plugin's own translation wins, then the core one, then the plugin's English text.
            if ($translator->hasForLocale($namespaced) || ! $translator->hasForLocale($key)) {
                $translated = __($namespaced, $replace);

                if (is_string($translated) && $translated !== $namespaced) {
                    return self::plain($translated);
                }
            }
        }

        $translated = __($key, $replace);

        return is_string($translated) ? self::plain($translated) : $key;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function icu(string $key, array $values = []): string
    {
        $translated = __($key);

        return Support\IcuMessage::format(is_string($translated) ? self::plain($translated) : $key, $values, app()->getLocale());
    }

    // A key with a prefix that has no text anywhere shows without the prefix.
    protected static function plain(string $text): string
    {
        return (string) preg_replace('/^[a-z0-9][a-z0-9-]*::/', '', $text);
    }
}
