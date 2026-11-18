<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Severity;

// SEO titles that are too short to explain the page or too long to show in full.
class TitleLength extends BaseCheck
{
    public function __construct(
        protected int $min,
        protected int $max,
    ) {}

    public function key(): string
    {
        return 'title-length';
    }

    public function label(): string
    {
        return __('Titles that are too long or too short');
    }

    public function description(): string
    {
        return __('Search engines show about :max characters of a title. Aim for :min to :max characters with the main words first.', ['min' => $this->min, 'max' => $this->max]);
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
            $length = mb_strlen($entry->seoTitle);

            if ($length > $this->max) {
                $items[] = $entry->item(message: '{length, plural, one {# character} other {# characters}}, search engines cut it after about {max}.', values: ['length' => $length, 'max' => $this->max]);
            } elseif ($length < $this->min) {
                $items[] = $entry->item(message: '{length, plural, one {# character} other {# characters}}, use at least {min} to say what the page is about.', values: ['length' => $length, 'min' => $this->min]);
            }
        }

        return CheckResult::of($items, count($entries), $context->maxItems);
    }
}
