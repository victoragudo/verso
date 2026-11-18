<?php

namespace App\Verso\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Content\Actions\CreateTerm;
use App\Verso\Content\Actions\DeleteTerm;
use App\Verso\Content\Actions\UpdateTerm;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\Http\Requests\TermRequest;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Core\Database\TextSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin page to manage the terms of one taxonomy.
class TermController extends Controller
{
    public function __construct(protected ContentLabels $labels) {}

    public function index(Request $request, Taxonomy $taxonomy): Response
    {
        Gate::authorize('viewAny', Term::class);

        $search = trim((string) $request->query('search'));

        $terms = Term::query()
            ->inTaxonomy($taxonomy->name)
            ->withCount('entries')
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['name'], $search))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString()
            ->through(fn (Term $term): array => [
                'id' => $term->id,
                'taxonomy' => $term->taxonomy,
                'name' => $term->name,
                'slug' => $term->slug,
                'parent_id' => $term->parent_id,
                'description' => $term->description,
                'entries_count' => (int) $term->getAttribute('entries_count'),
                'url' => $term->url(),
            ]);

        return Inertia::render('admin/content/terms/index', [
            'taxonomy' => $this->labels->taxonomyArray($taxonomy),
            'terms' => $terms,
            'parents' => $taxonomy->hierarchical
                ? Term::query()->inTaxonomy($taxonomy->name)->orderBy('name')->get(['id', 'name', 'parent_id'])
                    ->map(fn (Term $term): array => ['id' => $term->id, 'name' => $term->name, 'parent_id' => $term->parent_id])
                    ->all()
                : [],
            'filters' => ['search' => $search],
        ]);
    }

    public function store(TermRequest $request, CreateTerm $createTerm, Taxonomy $taxonomy): RedirectResponse
    {
        $createTerm->handle($taxonomy->name, $request->termData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type created.', ['type' => $this->labels->taxonomyLabel($taxonomy)])]);

        return back();
    }

    public function update(TermRequest $request, UpdateTerm $updateTerm, Taxonomy $taxonomy, Term $term): RedirectResponse
    {
        abort_unless($term->taxonomy === $taxonomy->name, 404);

        $updateTerm->handle($term, $request->termData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type saved.', ['type' => $this->labels->taxonomyLabel($taxonomy)])]);

        return back();
    }

    public function destroy(DeleteTerm $deleteTerm, Taxonomy $taxonomy, Term $term): RedirectResponse
    {
        abort_unless($term->taxonomy === $taxonomy->name, 404);
        Gate::authorize('delete', $term);

        $deleteTerm->handle($term);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type deleted.', ['type' => $this->labels->taxonomyLabel($taxonomy)])]);

        return back();
    }
}
