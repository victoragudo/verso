<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\Severity;

// No tagline and no default description, so the home page and lists have no description.
class NoSiteDescription extends BaseCheck
{
    public function key(): string
    {
        return 'no-site-description';
    }

    public function label(): string
    {
        return __('The site has no description');
    }

    public function description(): string
    {
        return __('Your home page and lists use the default description or the tagline. Write one sentence that says what the site is about.');
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::link(__('Write a description'), route('admin.site.permalinks.edit', ['tab' => 'search']));
    }

    public function run(AuditContext $context): CheckResult
    {
        return $context->tagline() === '' && $context->seo->defaultDescription() === null
            ? CheckResult::failed(message: 'No tagline and no default description.')
            : CheckResult::passed();
    }
}
