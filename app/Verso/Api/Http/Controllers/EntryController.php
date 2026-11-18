<?php

namespace App\Verso\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Api\Http\Requests\EntryIndexRequest;
use App\Verso\Api\Http\Resources\EntryResource;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Database\TextSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Public API for published entries of one content type.
class EntryController extends Controller
{
    public function index(EntryIndexRequest $request, TaxonomyRegistry $taxonomies, ContentType $contentType): AnonymousResourceCollection
    {
        $query = Entry::query()
            ->ofType($contentType->name)
            ->published()
            ->with(['author', 'terms']);

        $search = $request->search();
        TextSearch::apply($query, ['title', 'excerpt'], $search);
        $query->when($request->has('author'), fn (Builder $query) => $query->where('author_id', $request->integer('author')));
        $query->when($request->has('parent'), fn (Builder $query) => $query->where('parent_id', $request->integer('parent')));

        foreach ($contentType->taxonomies as $name) {
            $slugs = $this->slugsFrom($request->query($name));

            if ($taxonomies->has($name) && $slugs !== []) {
                $query->whereHas('terms', fn (Builder $terms) => $terms->where('taxonomy', $name)->whereIn('slug', $slugs));
            }
        }

        [$column, $direction] = $request->sort();

        $entries = $query->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate($request->perPage())
            ->withQueryString();

        return EntryResource::collection($entries);
    }

    // The slug can be a nested path for hierarchical types, like "about/team".
    public function show(ContentType $contentType, string $slug): EntryResource
    {
        $path = trim($slug, '/');
        $segments = explode('/', $path);

        $entry = Entry::query()
            ->ofType($contentType->name)
            ->published()
            ->where('slug', end($segments))
            ->with(['author', 'terms'])
            ->orderBy('id')
            ->get()
            ->first(fn (Entry $entry): bool => $entry->slugPath() === $path);

        abort_if($entry === null, 404);

        return EntryResource::make($entry)->withContent();
    }

    /**
     * Turn "travel,food" or ["travel", "food"] into a list of slugs.
     *
     * @return list<string>
     */
    protected function slugsFrom(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', is_string($value) ? $value : '');

        return array_values(array_filter(array_map(
            fn (mixed $slug): string => is_string($slug) ? trim($slug) : '',
            $values,
        )));
    }
}
