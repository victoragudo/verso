<?php

namespace App\Verso\Theme\Support;

use App\Verso\I18n\Translate;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;

class ThemeText
{
    public function __construct(protected ThemeManager $themes) {}

    public function text(Theme $theme, string $label): string
    {
        if ($label === '') {
            return '';
        }

        foreach ($this->themes->lineage($theme) as $member) {
            $key = $member->slug.'::'.$label;
            $translated = __($key);

            if (is_string($translated) && $translated !== $key && $translated !== $label) {
                return $translated;
            }
        }

        return Translate::text($label);
    }
}
