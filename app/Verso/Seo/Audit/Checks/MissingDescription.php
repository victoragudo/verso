<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Severity;

// Pages with no meta description and no excerpt, so search engines pick any text from the page.
class MissingDescription extends BaseCheck
{
    public function key(): string
    {
        return 'missing-description';
    }

    public function label(): string
    {
        return __('Pages without a meta description');
    }

    public function description(): string
    {
        return __('The description is the short text under your title in search results. Without one, search engines pick some text from the page. Write one or two sentences that make people want to click.');
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(AuditContext $context): CheckResult
    {
        $entries = $context->indexableEntries();
        $items = [];

        foreach ($entries as $entry) {
            if ($entry->description === null) {
                $items[] = $entry->item(message: 'No meta description and no excerpt.');
            }
        }

        return CheckResult::of($items, count($entries), $context->maxItems);
    }
}
