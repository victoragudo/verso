<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\EntrySnapshot;
use App\Verso\Seo\Audit\IssueItem;
use App\Verso\Seo\Audit\Severity;

// Pages that share the same SEO title, so search engines cannot tell them apart.
class DuplicateTitles extends BaseCheck
{
    protected const MESSAGES = [
        'title' => ['message' => 'Same title as {count, plural, one {# other page} other {# other pages}}.'],
        'description' => ['message' => 'Same description as {count, plural, one {# other page} other {# other pages}}.'],
    ];

    public function key(): string
    {
        return 'duplicate-titles';
    }

    public function label(): string
    {
        return __('Pages with the same title');
    }

    public function description(): string
    {
        return __('Each page should have its own title. When titles repeat, search engines may show the wrong page or none of them.');
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(AuditContext $context): CheckResult
    {
        $entries = $context->indexableEntries();

        return CheckResult::of($this->duplicates($entries, fn (EntrySnapshot $entry): string => $entry->seoTitle, self::MESSAGES['title']['message']), count($entries), $context->maxItems);
    }

    /**
     * Items for every entry whose value is used by another entry too. Case and spaces are ignored.
     *
     * @param  list<EntrySnapshot>  $entries
     * @param  callable(EntrySnapshot): ?string  $value
     * @return list<IssueItem>
     */
    protected function duplicates(array $entries, callable $value, string $message): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $text = $value($entry);

            if ($text !== null && trim($text) !== '') {
                $groups[mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)))][] = $entry;
            }
        }

        $items = [];

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            foreach ($group as $entry) {
                $others = count($group) - 1;
                $items[] = $entry->item($message, ['count' => $others]);
            }
        }

        return $items;
    }
}
