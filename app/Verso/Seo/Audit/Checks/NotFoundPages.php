<?php

namespace App\Verso\Seo\Audit\Checks;

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\IssueFix;
use App\Verso\Seo\Audit\IssueItem;
use App\Verso\Seo\Audit\Severity;
use App\Verso\Seo\Models\NotFoundHit;

// Addresses visitors asked for that do not exist. A redirect sends them to the right page.
class NotFoundPages extends BaseCheck
{
    protected const MESSAGES = [
        'visits' => ['message' => '{count, plural, one {# visit} other {# visits}}.'],
    ];

    public function key(): string
    {
        return 'not-found';
    }

    public function label(): string
    {
        return __('Visitors land on missing pages');
    }

    public function description(): string
    {
        return __('People and search engines asked for these addresses and got a "page not found" error. Old links often cause it. Send them to the right page with a redirect.');
    }

    public function severity(): Severity
    {
        return Severity::Notice;
    }

    public function fix(): ?IssueFix
    {
        return IssueFix::link(__('Open the 404 monitor'), route('admin.seo.not-found.index'));
    }

    public function run(AuditContext $context): CheckResult
    {
        $rows = NotFoundHit::query()->orderByDesc('hits')->orderBy('path')->limit(max(1, $context->maxItems))->get(['id', 'path', 'hits']);
        $count = NotFoundHit::query()->count();

        $items = $rows->map(fn (NotFoundHit $hit): IssueItem => new IssueItem(
            key: 'not-found-'.$hit->id,
            title: $hit->path,
            detail: self::MESSAGES['visits']['message'],
            editUrl: route('admin.seo.not-found.index', ['search' => $hit->path]),
            detailValues: ['count' => $hit->hits],
        ))->all();

        // Missing pages are compared with the published pages, so a few old links do not sink the score.
        return new CheckResult($count, max($count, count($context->entries())), array_values($items));
    }
}
