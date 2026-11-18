<?php

namespace App\Verso\Easy\Actions;

use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Support\EasyState;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Seo\SeoSettings;

// "Go live": lets search engines find the site and records when it went live.
class GoLive
{
    public function __construct(
        protected Settings $settings,
        protected EasyState $state,
    ) {}

    public function handle(): void
    {
        $this->settings->set(SeoSettings::DISCOURAGE, false);
        $this->state->rememberLaunch();

        SiteSettingsUpdated::dispatch([SeoSettings::DISCOURAGE, EasyState::LAUNCHED_AT]);
    }
}
