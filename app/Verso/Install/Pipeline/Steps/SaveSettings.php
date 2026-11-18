<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Simple\SimpleMode;
use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;
use App\Verso\Theme\Actions\ActivateTheme;

// Saves the site name, tagline, address, language, time zone and theme. New sites start in the simple admin mode.
class SaveSettings implements InstallStep
{
    public function __construct(
        protected Settings $settings,
        protected ActivateTheme $activateTheme,
        protected SimpleMode $simpleMode,
    ) {}

    public function key(): string
    {
        return 'settings';
    }

    public function label(): string
    {
        return __('Save the site settings');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return true;
    }

    public function handle(InstallPlan $plan): void
    {
        $this->settings->setMany([
            'site.name' => $plan->site->name,
            'site.tagline' => $plan->site->tagline,
            'site.url' => $plan->site->url,
            'site.locale' => $plan->site->locale,
            'site.timezone' => $plan->site->timezone,
        ]);
        $this->simpleMode->rememberNewSiteDefault();

        $this->activateTheme->handle($plan->content->theme);
    }
}
