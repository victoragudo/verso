<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Models\Redirect;
use App\Verso\Site\Http\Requests\RedirectRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin page to list, add and delete redirects, with their hit counts.
class RedirectController extends Controller
{
    /** @var list<string> */
    public const SORTS = ['recent', 'hits', 'path'];

    public function index(Request $request): Response
    {
        Gate::authorize(Capability::ManagePermalinks->value);

        $search = trim((string) $request->query('search'));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : self::SORTS[0];

        $redirects = Redirect::query()
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['from_path', 'to_path'], $search))
            ->when($sort === 'hits', fn (Builder $query) => $query->orderByDesc('hits')->orderBy('from_path'))
            ->when($sort === 'path', fn (Builder $query) => $query->orderBy('from_path'))
            ->when($sort === 'recent', fn (Builder $query) => $query->latest('id'))
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString()
            ->through(fn (Redirect $redirect): array => [
                'id' => $redirect->id,
                'from_path' => $redirect->from_path,
                'to_path' => $redirect->to_path,
                'status_code' => $redirect->status_code,
                'hits' => $redirect->hits,
                'created_at' => $redirect->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/site/redirects', [
            'redirects' => $redirects,
            'filters' => ['search' => $search, 'sort' => $sort],
            'statusCodes' => Redirect::ALLOWED_STATUS_CODES,
            'defaultStatus' => (int) config('verso.redirects.default_status'),
            'totalHits' => (int) Redirect::query()->sum('hits'),
        ]);
    }

    public function store(RedirectRequest $request): RedirectResponse
    {
        $redirect = Redirect::point(
            $request->string('from_path')->toString(),
            $request->string('to_path')->toString(),
            $request->integer('status_code'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect from :path saved.', ['path' => $redirect->from_path])]);

        return to_route('admin.site.redirects.index');
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        Gate::authorize(Capability::ManagePermalinks->value);

        $redirect->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect deleted.')]);

        return back();
    }
}
