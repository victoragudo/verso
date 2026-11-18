<?php

namespace App\Verso\Fields\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Media\Http\Presenters\MediaPresenter;
use App\Verso\Media\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// Server search and label lookup for relation, image and file fields in the entry editor.
class FieldLookupController extends Controller
{
    public const array KINDS = ['entry', 'term', 'user', 'media'];

    public function __invoke(Request $request, MediaPresenter $media): JsonResponse
    {
        Gate::authorize('viewAny', Entry::class);

        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'q' => ['nullable', 'string', 'max:190'],
            'ids' => ['nullable', 'array', 'max:'.(int) config('verso.fields.max_rows')],
            'ids.*' => ['integer', 'min:1'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'max:80'],
            'taxonomy' => ['nullable', 'string', 'max:80'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:40'],
        ]);

        $ids = array_values(array_map(intval(...), (array) ($data['ids'] ?? [])));
        $search = trim((string) ($data['q'] ?? ''));
        $limit = (int) config('verso.fields.search_limit');

        if ($data['kind'] === 'media') {
            $items = Media::query()->whereKey($ids)->get()
                ->map(fn (Media $item): array => $media->item($item))->values()->all();

            return response()->json(['items' => $items]);
        }

        $items = match ($data['kind']) {
            'entry' => $this->entries($ids, $search, $limit, array_values(array_map(strval(...), (array) ($data['types'] ?? [])))),
            'term' => $this->terms($ids, $search, $limit, (string) ($data['taxonomy'] ?? '')),
            default => $this->users($ids, $search, $limit, array_values(array_map(strval(...), (array) ($data['roles'] ?? [])))),
        };

        return response()->json(['items' => $items]);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $types
     * @return list<array{value: string, label: string, hint: string}>
     */
    protected function entries(array $ids, string $search, int $limit, array $types): array
    {
        $query = Entry::query()->when($types !== [], fn (Builder $query) => $query->whereIn('type', $types));

        return array_values($this->narrow($query, $ids, $search, $limit, 'title')->get()
            ->map(fn (Entry $entry): array => ['value' => (string) $entry->id, 'label' => $entry->title, 'hint' => $entry->status->label()])
            ->all());
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{value: string, label: string, hint: string}>
     */
    protected function terms(array $ids, string $search, int $limit, string $taxonomy): array
    {
        $query = Term::query()->where('taxonomy', $taxonomy);

        return array_values($this->narrow($query, $ids, $search, $limit, 'name')->get()
            ->map(fn (Term $term): array => ['value' => (string) $term->id, 'label' => $term->name, 'hint' => $term->slug])
            ->all());
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $roles
     * @return list<array{value: string, label: string, hint: string}>
     */
    protected function users(array $ids, string $search, int $limit, array $roles): array
    {
        $query = User::query()->when($roles !== [], fn (Builder $query) => $query->whereIn('role', $roles));

        return array_values($this->narrow($query, $ids, $search, $limit, 'name')->get()
            ->map(fn (User $user): array => ['value' => (string) $user->id, 'label' => $user->name, 'hint' => $user->role->label()])
            ->all());
    }

    /**
     * Known ids give their labels; otherwise search by the text column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>  $ids
     * @param  'title'|'name'  $column
     * @return Builder<TModel>
     */
    protected function narrow(Builder $query, array $ids, string $search, int $limit, string $column): Builder
    {
        if ($ids !== []) {
            return $query->whereKey($ids);
        }

        TextSearch::apply($query, [$column], $search);

        return $query->orderBy($column)->orderBy('id')->limit($limit);
    }
}
