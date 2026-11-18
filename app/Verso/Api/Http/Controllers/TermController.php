<?php

namespace App\Verso\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Api\Http\Resources\TermResource;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Public API for the terms of one taxonomy, with counts of published entries.
class TermController extends Controller
{
    public function index(Request $request, Taxonomy $taxonomy): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('verso.api.max_per_page')],
            'parent' => ['sometimes', 'integer'],
        ]);

        $terms = $this->query($taxonomy)
            ->when($request->has('parent'), fn (Builder $query) => $query->where('parent_id', $request->integer('parent')))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', (int) config('verso.api.per_page')))
            ->withQueryString();

        return TermResource::collection($terms);
    }

    public function show(Taxonomy $taxonomy, string $slug): TermResource
    {
        return TermResource::make($this->query($taxonomy)->where('slug', $slug)->firstOrFail());
    }

    /**
     * @return Builder<Term>
     */
    protected function query(Taxonomy $taxonomy): Builder
    {
        return Term::query()
            ->inTaxonomy($taxonomy->name)
            ->withCount(['entries' => fn (Builder $query) => $query->whereIn(
                'entries.id',
                Entry::query()->published()->select('id'),
            )]);
    }
}
