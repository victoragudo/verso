<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Site\Actions\CreateMenu;
use App\Verso\Site\Actions\DeleteMenu;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Actions\UpdateMenu;
use App\Verso\Site\Enums\MenuItemVisibility;
use App\Verso\Site\Http\Requests\MenuItemsRequest;
use App\Verso\Site\Http\Requests\MenuRequest;
use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Menus\MenuRenderer;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// Admin menu builder: create menus, pick their location and edit their nested items.
class MenuController extends Controller
{
    public function __construct(
        protected MenuLocations $locations,
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected ContentLabels $labels,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize(Capability::ManageMenus->value);

        $menus = Menu::query()->withCount('items')->orderBy('name')->get();
        $selected = $menus->firstWhere('id', $request->integer('menu')) ?? $menus->first();

        return Inertia::render('admin/site/menus', [
            'menus' => $menus->map(fn (Menu $menu): array => [
                'id' => $menu->id,
                'name' => $menu->name,
                'location' => $menu->location,
                'items_count' => (int) $menu->getAttribute('items_count'),
            ])->all(),
            'menu' => $selected === null ? null : [
                'id' => $selected->id,
                'name' => $selected->name,
                'location' => $selected->location,
                'items' => $this->nodes($selected->tree(MenuRenderer::RELATIONS)),
            ],
            'locations' => $this->locations->toList(),
            'sources' => $this->sources(),
            'maxDepth' => (int) config('verso.menus.max_depth'),
            'relWords' => SyncMenuItems::REL_WORDS,
            'visibilities' => array_map(fn (MenuItemVisibility $visibility): array => ['value' => $visibility->value, 'label' => $visibility->label()], MenuItemVisibility::cases()),
        ]);
    }

    // JSON search for the picker: published entries of a type or terms of a taxonomy whose title matches.
    public function search(Request $request): JsonResponse
    {
        Gate::authorize(Capability::ManageMenus->value);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['entry', 'term'])],
            'source' => ['required', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:190'],
        ]);

        $source = (string) $data['source'];
        $search = trim((string) ($data['q'] ?? ''));

        if ($data['kind'] === 'entry') {
            abort_unless($this->types->has($source), 404);
            $items = $this->entryItems($source, $search);
        } else {
            abort_unless($this->taxonomies->has($source), 404);
            $items = $this->termItems($source, $search);
        }

        return response()->json($items);
    }

    public function store(MenuRequest $request, CreateMenu $createMenu): RedirectResponse
    {
        $menu = $createMenu->handle((string) $request->validated('name'), $request->location());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu “:name” created.', ['name' => $menu->name])]);

        return to_route('admin.site.menus.index', ['menu' => $menu->id]);
    }

    public function update(MenuRequest $request, UpdateMenu $updateMenu, Menu $menu): RedirectResponse
    {
        $updateMenu->handle($menu, (string) $request->validated('name'), $request->location());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu saved.')]);

        return to_route('admin.site.menus.index', ['menu' => $menu->id]);
    }

    public function items(MenuItemsRequest $request, SyncMenuItems $syncItems, Menu $menu): RedirectResponse
    {
        $syncItems->handle($menu, $request->items());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu items saved.')]);

        return to_route('admin.site.menus.index', ['menu' => $menu->id]);
    }

    public function destroy(DeleteMenu $deleteMenu, Menu $menu): RedirectResponse
    {
        Gate::authorize(Capability::ManageMenus->value);

        $deleteMenu->handle($menu);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Menu deleted.')]);

        return to_route('admin.site.menus.index');
    }

    /**
     * The item tree for the builder.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return list<array<string, mixed>>
     */
    protected function nodes(Collection $items): array
    {
        $nodes = [];

        foreach ($items as $item) {
            $nodes[] = [
                'id' => $item->id,
                'type' => $item->type->value,
                'entry_id' => $item->entry_id,
                'term_id' => $item->term_id,
                'label' => $item->label,
                'url' => $item->url,
                'open_in_new_tab' => $item->open_in_new_tab,
                'mega' => $item->mega,
                'rel' => $item->rel === null ? [] : explode(' ', $item->rel),
                'visibility' => $item->visibility->value,
                'title' => $item->resolvedLabel(),
                'visible' => $item->isVisible(),
                'children' => $this->nodes($item->children),
            ];
        }

        return $nodes;
    }

    /**
     * Published entries of each content type and terms of each taxonomy, to add to a menu.
     *
     * @return array{entries: list<array<string, mixed>>, terms: list<array<string, mixed>>}
     */
    protected function sources(): array
    {
        $entries = [];
        $terms = [];

        foreach ($this->types->all() as $type) {
            $entries[] = [
                'kind' => 'entry',
                'name' => $type->name,
                'label' => $this->labels->typePluralLabel($type),
                ...$this->entryItems($type->name),
            ];
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            $terms[] = [
                'kind' => 'term',
                'name' => $taxonomy->name,
                'label' => $this->labels->taxonomyPluralLabel($taxonomy),
                ...$this->termItems($taxonomy->name),
            ];
        }

        return ['entries' => $entries, 'terms' => $terms];
    }

    /**
     * Published entries of a type, matching the search when given.
     *
     * @return array{items: list<array{id: int, title: string}>, hasMore: bool}
     */
    protected function entryItems(string $type, string $search = ''): array
    {
        $query = Entry::query()->ofType($type)->published()->orderBy('title');

        return $this->pickerItems($this->matching($query, 'title', $search), 'title');
    }

    /**
     * Terms of a taxonomy, matching the search when given.
     *
     * @return array{items: list<array{id: int, title: string}>, hasMore: bool}
     */
    protected function termItems(string $taxonomy, string $search = ''): array
    {
        $query = Term::query()->inTaxonomy($taxonomy)->orderBy('name');

        return $this->pickerItems($this->matching($query, 'name', $search), 'name');
    }

    /**
     * Take one row more than the limit to know if the list was cut.
     *
     * @template TModel of Entry|Term
     *
     * @param  Builder<TModel>  $query
     * @return array{items: list<array{id: int, title: string}>, hasMore: bool}
     */
    protected function pickerItems(Builder $query, string $column): array
    {
        $limit = max((int) config('verso.menus.picker_limit'), 1);
        $rows = $query->limit($limit + 1)->get(['id', $column]);

        return [
            'items' => array_values($rows->take($limit)
                ->map(fn (Entry|Term $row): array => ['id' => (int) $row->getKey(), 'title' => (string) $row->getAttribute($column)])
                ->all()),
            'hasMore' => $rows->count() > $limit,
        ];
    }

    /**
     * Case insensitive "contains" filter on one column.
     *
     * @template TModel of Entry|Term
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function matching(Builder $query, string $column, string $search): Builder
    {
        if ($search === '') {
            return $query;
        }

        return $query->whereLike($column, '%'.$search.'%');
    }
}
