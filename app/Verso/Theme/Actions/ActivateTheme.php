<?php

namespace App\Verso\Theme\Actions;

use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;

// Makes a theme the active one. The settings change also empties the page cache.
class ActivateTheme
{
    public function __construct(protected ThemeManager $themes) {}

    public function handle(string $slug): Theme
    {
        return $this->themes->activate($slug);
    }
}
