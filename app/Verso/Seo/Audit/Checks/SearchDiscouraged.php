<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Settings\Settings;
use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\FixableCheck;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Seo\SeoSettings;

// The whole site asks search engines not to show it. Right while building a site, wrong once it is live.
class SearchDiscouraged extends BaseCheck implements FixableCheck
{
    public function __construct(
        protected SeoSettings $seo,
        protected Settings $settings,
    ) {}

    public function key(): string
    {
        return 'search-discouraged';
    }

    public function label(): string
    {
        return __('Search engines are asked to stay away');
    }

    public function description(): string
    {
        return __('The "Discourage search engines" switch is on, so no page of the site shows up in search results. Turn it off when the site is ready.');
    }

    public function severity(): Severity
    {
        return Severity::Critical;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::action(__('Let search engines in'));
    }

    public function fixCapability(): Capability
    {
        return Capability::ManagePermalinks;
    }

    public function run(AuditContext $context): CheckResult
    {
        return $this->seo->discourage() ? CheckResult::failed(message: 'Every page tells search engines not to show it.') : CheckResult::passed();
    }

    public function applyFix(): string
    {
        $this->settings->set(SeoSettings::DISCOURAGE, false);
        event(new SiteSettingsUpdated([SeoSettings::DISCOURAGE]));

        return __('Search engines can now show your site.');
    }
}
