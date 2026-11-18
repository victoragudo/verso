<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\Severity;

// Posts without a featured or share image. Links to them look empty on social networks.
class MissingImage extends BaseCheck
{
    public function key(): string
    {
        return 'missing-image';
    }

    public function label(): string
    {
        return __('Posts without a featured or share image');
    }

    public function description(): string
    {
        return __('When someone shares a post, social networks show its image. Add a featured image, or set a default share image for the whole site.');
    }

    public function severity(): Severity
    {
        return Severity::Notice;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::link(__('Set a default share image'), route('admin.site.permalinks.edit', ['tab' => 'search']));
    }

    public function run(AuditContext $context): CheckResult
    {
        $articles = array_values(array_filter($context->indexableEntries(), fn ($entry): bool => $entry->isArticle));

        // With a default share image every link still gets a picture.
        if ($context->seo->defaultImageId() !== null) {
            return CheckResult::passed(count($articles));
        }

        $items = [];

        foreach ($articles as $entry) {
            if (! $entry->hasImage) {
                $items[] = $entry->item(message: 'No image to show when it is shared.');
            }
        }

        return CheckResult::of($items, count($articles), $context->maxItems);
    }
}
