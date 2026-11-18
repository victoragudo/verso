<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\EntrySnapshot;
use App\Verso\Seo\Audit\Severity;

// Pages that share the same meta description.
class DuplicateDescriptions extends DuplicateTitles
{
    public function key(): string
    {
        return 'duplicate-descriptions';
    }

    public function label(): string
    {
        return __('Pages with the same description');
    }

    public function description(): string
    {
        return __('A description that repeats on many pages says little about each one. Give every page its own short summary.');
    }

    public function severity(): Severity
    {
        return Severity::Notice;
    }

    public function run(AuditContext $context): CheckResult
    {
        $entries = $context->indexableEntries();

        return CheckResult::of($this->duplicates($entries, fn (EntrySnapshot $entry): ?string => $entry->description, self::MESSAGES['description']['message']), count($entries), $context->maxItems);
    }
}
