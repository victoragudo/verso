<?php

namespace App\Verso\Seo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Support\SiteTime;
use App\Verso\I18n\Translate;
use App\Verso\Seo\Audit\SeoAudit;
use App\Verso\Seo\Http\Requests\NotFoundRedirectRequest;
use App\Verso\Seo\Models\NotFoundHit;
use App\Verso\Seo\NotFound\NotFoundSuggestions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// The 404 monitor: missing addresses with their hits, close matches and a button to redirect them.
class NotFoundController extends Controller
{
    /** @var list<string> */
    public const SORTS = ['hits', 'recent', 'path'];

    public function __construct(protected SeoAudit $audit) {}

    public function index(Request $request, NotFoundSuggestions $suggestions, SiteTime $time): Response
    {
        $search = trim((string) $request->query('search'));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : self::SORTS[0];

        $page = NotFoundHit::query()
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['path', 'referrer_host'], $search))
            ->when($sort === 'hits', fn (Builder $query) => $query->orderByDesc('hits')->orderBy('path'))
            ->when($sort === 'recent', fn (Builder $query) => $query->orderByDesc('last_seen_at')->orderByDesc('id'))
            ->when($sort === 'path', fn (Builder $query) => $query->orderBy('path')->orderBy('id'))
            ->paginate(max(1, (int) config('verso.seo.not_found.per_page')))
            ->withQueryString();

        /** @var list<NotFoundHit> $rows */
        $rows = $page->items();
        $paths = array_map(fn (NotFoundHit $hit): string => $hit->path, $rows);
        $redirects = Redirect::query()->whereIn('from_path', $paths)->pluck('to_path', 'from_path')->all();
        $matches = $suggestions->forMany($paths);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/seo/not-found', [
            'rows' => $page->through(fn (NotFoundHit $hit): array => [
                'id' => $hit->id,
                'path' => $hit->path,
                'hits' => $hit->hits,
                'referrer_host' => $hit->referrer_host,
                'first_seen_at' => $time->iso($hit->first_seen_at),
                'last_seen_at' => $time->iso($hit->last_seen_at),
                'redirect' => $redirects[$hit->path] ?? null,
                'suggestions' => $matches[$hit->path] ?? [],
            ]),
            'filters' => ['search' => $search, 'sort' => $sort],
            'totals' => ['paths' => NotFoundHit::query()->count(), 'hits' => (int) NotFoundHit::query()->sum('hits')],
            'settings' => [
                'enabled' => (bool) config('verso.seo.not_found.enabled'),
                'retentionDays' => (int) config('verso.seo.not_found.retention_days'),
                'maxRows' => (int) config('verso.seo.not_found.max_rows'),
            ],
            'statusCodes' => Redirect::ALLOWED_STATUS_CODES,
            'defaultStatus' => (int) config('verso.redirects.default_status'),
            'can' => ['manage' => $user->can(Capability::ManageSeo->value)],
        ]);
    }

    // Uses the existing redirects. The row is removed because the address now leads somewhere.
    public function redirect(NotFoundRedirectRequest $request, NotFoundHit $hit): RedirectResponse
    {
        $redirect = Redirect::point(
            $hit->path,
            $request->string('to_path')->toString(),
            $request->filled('status_code') ? $request->integer('status_code') : null,
        );

        $hit->delete();
        $this->audit->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':from now redirects to :to.', ['from' => $redirect->from_path, 'to' => $redirect->to_path])]);

        return back();
    }

    public function destroy(NotFoundHit $hit): RedirectResponse
    {
        $hit->delete();
        $this->audit->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address removed from the list.')]);

        return back();
    }

    public function clear(): RedirectResponse
    {
        $count = NotFoundHit::query()->delete();
        $this->audit->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('Cleared {count, plural, one {# address} other {# addresses}}.', ['count' => $count])]);

        return back();
    }
}
