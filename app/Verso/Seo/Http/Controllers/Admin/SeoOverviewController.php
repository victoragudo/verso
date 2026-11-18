<?php

namespace App\Verso\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Seo\Actions\StartLinkCheck;
use App\Verso\Seo\Audit\CheckResult;
use App\Verso\Seo\Audit\FixableCheck;
use App\Verso\Seo\Audit\IssueItem;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Audit\SeoCheck;
use App\Verso\Seo\Audit\SeoCheckRegistry;
use App\Verso\Seo\Links\CrawlState;
use App\Verso\Seo\Models\NotFoundHit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// The SEO overview: the site score, the list of issues, one page per issue and the one click fixes.
class SeoOverviewController extends Controller
{
    // Rows of the 404 preview on the overview.
    protected const NOT_FOUND_PREVIEW = 5;

    public function __construct(
        protected SeoAudit $audit,
        protected SeoCheckRegistry $checks,
        protected CrawlState $crawl,
    ) {}

    public function index(Request $request): Response
    {
        $report = $this->audit->report();
        /** @var User $user */
        $user = $request->user();
        $checks = array_map(fn (SeoCheck $check): array => $this->checkData($check, $report->result($check->key()) ?? CheckResult::passed(), $user), $this->checks->all());

        usort($checks, fn (array $a, array $b): int => [$b['affected'] > 0, $b['weight'], $b['affected']] <=> [$a['affected'] > 0, $a['weight'], $a['affected']]);

        return Inertia::render('admin/seo/overview', [
            'score' => $this->audit->score($report),
            'thresholds' => ['good' => (int) config('verso.seo.analysis.good_score'), 'ok' => (int) config('verso.seo.analysis.ok_score')],
            'checkedAt' => $report->checkedAt,
            'checks' => $checks,
            'ratings' => $report->ratings,
            'linkCheck' => [...$this->crawl->current(), 'running' => $this->crawl->isRunning()],
            'notFound' => [
                'total' => NotFoundHit::query()->count(),
                'hits' => (int) NotFoundHit::query()->sum('hits'),
                'top' => NotFoundHit::query()->orderByDesc('hits')->orderBy('path')->limit(self::NOT_FOUND_PREVIEW)->get(['id', 'path', 'hits'])
                    ->map(fn (NotFoundHit $hit): array => ['id' => $hit->id, 'path' => $hit->path, 'hits' => $hit->hits])->all(),
                'enabled' => (bool) config('verso.seo.not_found.enabled'),
            ],
            'can' => ['manage' => $user->can(Capability::ManageSeo->value)],
        ]);
    }

    public function show(Request $request, ContentTypeRegistry $types, ContentLabels $labels, string $check): Response
    {
        $found = $this->findCheck($check);
        $result = $this->audit->report()->result($found->key()) ?? CheckResult::passed();
        /** @var User $user */
        $user = $request->user();
        $type = (string) $request->query('type', '');
        $search = trim((string) $request->query('search', ''));

        $typeLabel = function (IssueItem $item) use ($types, $labels): ?string {
            $contentType = $item->type === null ? null : $types->find($item->type);

            return $contentType === null ? $item->typeLabel : $labels->typeLabel($contentType);
        };
        $rows = array_map(fn (IssueItem $item): array => $item->display($typeLabel($item)), $result->items);
        $rows = array_values(array_filter($rows, fn (array $row): bool => ($type === '' || $row['type'] === $type)
            && ($search === '' || Str::contains(Str::lower($row['title'].' '.$row['detail']), Str::lower($search)))));
        $perPage = max(1, (int) config('verso.seo.audit.per_page'));
        $page = max(1, (int) $request->query('page', '1'));
        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $typeOptions = [];
        foreach ($result->items as $item) {
            if ($item->type !== null) {
                $typeOptions[$item->type] = $item->typeLabel ?? $item->type;
            }
        }

        return Inertia::render('admin/seo/issue', [
            'check' => $this->checkData($found, $result, $user),
            'items' => $paginator,
            'filters' => ['type' => $type, 'search' => $search],
            'types' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => ($contentType = $types->find($value)) === null ? $label : $labels->typePluralLabel($contentType)], array_keys($typeOptions), array_values($typeOptions)),
            'shown' => count($result->items),
            'linkCheck' => [...$this->crawl->current(), 'running' => $this->crawl->isRunning()],
            'can' => ['manage' => $user->can(Capability::ManageSeo->value)],
        ]);
    }

    public function fix(Request $request, string $check): RedirectResponse
    {
        $found = $this->findCheck($check);
        /** @var User $user */
        $user = $request->user();

        abort_unless($found instanceof FixableCheck, 404);
        abort_unless($user->can($found->fixCapability()->value), 403);

        $message = $found->applyFix();
        $this->audit->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function refresh(): RedirectResponse
    {
        $this->audit->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The SEO overview is up to date.')]);

        return back();
    }

    public function checkLinks(StartLinkCheck $start): RedirectResponse
    {
        $started = $start->handle();

        Inertia::flash('toast', $started
            ? ['type' => 'success', 'message' => __('The link check has started. It runs in the background.')]
            : ['type' => 'info', 'message' => __('A link check is already running.')]);

        return back();
    }

    protected function findCheck(string $key): SeoCheck
    {
        $check = $this->checks->find($key);
        abort_if($check === null, 404);

        return $check;
    }

    /** @return array<string, mixed> */
    protected function checkData(SeoCheck $check, CheckResult $result, User $user): array
    {
        $fix = $check->fix()?->toArray();
        $canFix = $user->can(Capability::ManageSeo->value)
            && (! $check instanceof FixableCheck || $user->can($check->fixCapability()->value));

        return [
            'key' => $check->key(),
            'label' => $check->label(),
            'description' => $check->description(),
            'severity' => $check->severity()->value,
            'severityLabel' => $check->severity()->label(),
            'weight' => $check->severity()->weight(),
            'affected' => $result->affected,
            'total' => $result->total,
            'pending' => $result->pending,
            'summary' => $result->displaySummary(),
            'hasItems' => $result->items !== [],
            'fix' => $fix === null || ($fix['kind'] === 'action' && ! $canFix) ? null : $fix,
            'href' => route('admin.seo.issues.show', $check->key()),
            'fixUrl' => $check instanceof FixableCheck && $canFix ? route('admin.seo.issues.fix', $check->key()) : null,
        ];
    }
}
