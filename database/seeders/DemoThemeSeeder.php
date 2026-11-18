<?php

namespace Database\Seeders;

use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Database\Seeder;

// Turns on the demo theme (verso.demo.theme) when it is installed. The default theme stays when it is missing.
class DemoThemeSeeder extends Seeder
{
    public function run(ThemeManager $themes, ActivateTheme $activateTheme): void
    {
        $slug = (string) config('verso.demo.theme');

        if ($slug === '' || ! $themes->exists($slug)) {
            $this->command?->warn("Skipped the demo theme \"{$slug}\": it is not installed.");

            return;
        }

        $activateTheme->handle($slug);
    }
}
