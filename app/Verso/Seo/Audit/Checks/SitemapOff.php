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
use App\Verso\Site\Sitemap\SitemapSettings;

// The XML sitemap is turned off, so search engines find new pages more slowly.
class SitemapOff extends BaseCheck implements FixableCheck
{
    public function __construct(
        protected SitemapSettings $sitemap,
        protected Settings $settings,
    ) {}

    public function key(): string
    {
        return 'sitemap-off';
    }

    public function label(): string
    {
        return __('The sitemap is off');
    }

    public function description(): string
    {
        return __('The sitemap is a list of all your pages for search engines. With it on, new pages are found sooner.');
    }

    public function severity(): Severity
    {
        return Severity::Critical;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::action(__('Turn the sitemap on'));
    }

    public function fixCapability(): Capability
    {
        return Capability::ManagePermalinks;
    }

    public function run(AuditContext $context): CheckResult
    {
        return $this->sitemap->enabled() ? CheckResult::passed() : CheckResult::failed(message: 'Search engines get no list of your pages.');
    }

    public function applyFix(): string
    {
        $this->settings->set(SitemapSettings::ENABLED, true);
        event(new SiteSettingsUpdated([SitemapSettings::ENABLED]));

        return __('The sitemap is on.');
    }
}
