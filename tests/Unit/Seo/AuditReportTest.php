<?php

use App\Verso\Seo\Audit\AuditContext;
use App\Verso\Seo\Audit\AuditReport;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\Checks\BaseCheck;
use App\Verso\Seo\Audit\IssueItem;
use App\Verso\Seo\Audit\SeoCheckRegistry;
use App\Verso\Seo\Audit\Severity;

function fakeCheck(string $key, Severity $severity): BaseCheck
{
    return new class($key, $severity) extends BaseCheck
    {
        public function __construct(protected string $id, protected Severity $level) {}

        public function key(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return 'Check '.$this->id;
        }

        public function description(): string
        {
            return '';
        }

        public function severity(): Severity
        {
            return $this->level;
        }

        public function run(AuditContext $context): CheckResult
        {
            return CheckResult::passed();
        }
    };
}

it('weighs the score by severity and the share of affected items', function () {
    $checks = [fakeCheck('switch', Severity::Critical), fakeCheck('titles', Severity::Warning), fakeCheck('images', Severity::Notice)];
    $report = new AuditReport([
        'switch' => CheckResult::passed(),
        'titles' => new CheckResult(5, 10),
        'images' => CheckResult::failed('No images.'),
    ], '2026-11-17T10:00:00+00:00');

    // Lost: warning 3 * 0.5 + notice 1 * 1 = 2.5 of 9.
    expect($report->score($checks))->toBe(72);
});

it('leaves pending checks out of the score', function () {
    $checks = [fakeCheck('links', Severity::Critical), fakeCheck('titles', Severity::Warning)];
    $report = new AuditReport(['links' => CheckResult::pending('Run the link check.'), 'titles' => CheckResult::passed(4)], '');

    expect($report->score($checks))->toBe(100)
        ->and((new AuditReport([], ''))->score([]))->toBe(100);
});

it('survives a round trip through the cache format', function () {
    $report = new AuditReport(
        ['titles' => CheckResult::of([new IssueItem('entry-4', 'Rye bread', 'Too long.', '/admin/entries/post/4/edit', type: 'post', typeLabel: 'Post')], 12, 10)],
        '2026-11-17T10:00:00+00:00',
        ['good' => 3, 'ok' => 1, 'poor' => 0, 'none' => 8],
    );

    $copy = AuditReport::fromArray(json_decode(json_encode($report->toArray()), true));

    expect($copy->toArray())->toBe($report->toArray())
        ->and($copy->result('titles')->items[0]->typeLabel)->toBe('Post');
});

it('keeps exact counts but limits the listed items', function () {
    $items = array_map(fn (int $number): IssueItem => new IssueItem('entry-'.$number, 'Post '.$number), range(1, 5));
    $result = CheckResult::of($items, 20, 2);

    expect($result->affected)->toBe(5)->and($result->items)->toHaveCount(2)->and($result->penalty())->toBe(0.25);
});

it('registers, replaces and removes checks', function () {
    $registry = new SeoCheckRegistry;
    $registry->add(fakeCheck('titles', Severity::Warning), fakeCheck('titles', Severity::Notice), fakeCheck('images', Severity::Notice));

    expect($registry->all())->toHaveCount(2)
        ->and($registry->find('titles')->severity())->toBe(Severity::Notice);

    $registry->remove('images');

    expect($registry->find('images'))->toBeNull();
    expect(fn () => $registry->add(fakeCheck('Bad Key', Severity::Notice)))->toThrow(InvalidArgumentException::class);
});

it('keeps the English messages in the cached report and fills them when shown', function () {
    $item = new IssueItem('entry-9', '', 'Same title as {count, plural, one {# other page} other {# other pages}}.', '/admin/entries/post/9/edit', detailValues: ['count' => 3]);
    $report = AuditReport::fromArray((new AuditReport([
        'titles' => CheckResult::of([$item], 4, 10),
        'links' => CheckResult::pending('Run the link check to find {what}.', ['what' => 'broken links']),
    ], '2026-11-18T09:30:00+00:00'))->toArray());

    $cached = $report->result('titles')->items[0];

    expect($cached->detail)->toBe('Same title as {count, plural, one {# other page} other {# other pages}}.')
        ->and($cached->display()['detail'])->toBe('Same title as 3 other pages.')
        ->and($cached->display()['title'])->toBe('Untitled')
        ->and($report->result('links')->displaySummary())->toBe('Run the link check to find broken links.');
});
