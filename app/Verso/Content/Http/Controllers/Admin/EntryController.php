<?php

namespace App\Verso\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Access\Permissions\EntryPermissions;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Actions\RestoreEntry;
use App\Verso\Content\Actions\TrashEntry;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\Admin\EntryListColumns;
use App\Verso\Content\ContentType;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Http\Presenters\EntryPresenter;
use App\Verso\Content\Http\Requests\EntryRequest;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Admin\ListColumn;
use App\Verso\Core\Database\TextSearch;
use App\Verso\Editor\Blocks\SectionBlock;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Support\EntryFieldsForm;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\EntryPanels\EntryPanelRegistry;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\Templates\PageTemplates;
use App\Verso\Theme\Templates\TemplatePartChoices;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin pages to list, create, edit, trash and restore entries of one content type.
class EntryController extends Controller
{
    public function __construct(
        protected EntryPresenter $presenter,
        protected TaxonomyRegistry $taxonomies,
        protected PluginBlockRegistry $pluginBlocks,
        protected EntryListColumns $listColumns,
        protected EntryPermissions $entryPermissions,
        protected ContentLabels $labels,
    ) {}

    public function index(Request $request, #[CurrentUser] User $user, ContentType $contentType): Response
    {
        Gate::authorize('viewAny', [Entry::class, $contentType->name]);

        $status = EntryStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));
        $ownOrPublished = ! $this->entryPermissions->canReadPrivate($user, $contentType->name) ? $user : null;
        $counts = $this->statusCounts($contentType, $ownOrPublished);
        $perPage = (int) config('verso.admin.per_page');

        $query = Entry::query()
            ->ofType($contentType->name)
            ->tap(fn (Builder $query) => $this->limitToVisible($query, $ownOrPublished))
            ->with('author:id,name')
            ->when($status === EntryStatus::Trash, fn (Builder $query) => $query->onlyTrashed())
            ->when($status !== null && $status !== EntryStatus::Trash, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => TextSearch::apply($query, ['title'], $search))
            ->when(
                $contentType->hierarchical,
                fn (Builder $query) => $query->orderBy('menu_order')->orderBy('title')->orderBy('id'),
                fn (Builder $query) => $query->latest('updated_at')->latest('id'),
            );
        $this->listColumns->eagerLoad($query, $contentType);

        // Without a search the status counts already hold the total, so the list needs no extra count query.
        $entries = $search === ''
            ? $this->paginateWithTotal($query, $counts[$status->value ?? 'all'] ?? 0, $perPage, $request)
            : $query->paginate($perPage)->withQueryString();

        $this->listColumns->preload(array_values($entries->items()));

        return Inertia::render('admin/content/entries/index', [
            'contentType' => $this->labels->typeArray($contentType),
            'entries' => $entries->through(fn (Entry $entry): array => $this->presenter->row($entry, $user)),
            'filters' => ['status' => $status?->value, 'search' => $search],
            'counts' => $counts,
            'can' => ['create' => $user->can('create', [Entry::class, $contentType->name])],
            'columns' => array_map(fn (ListColumn $column): array => $column->toArray(), $this->listColumns->for($contentType)),
            'listView' => AdminPreferences::of($user)->listViewProps('entries.'.$contentType->name),
        ]);
    }

    /**
     * One page of the query with a total that is already known.
     *
     * @param  Builder<Entry>  $query
     * @return LengthAwarePaginator<int, Entry>
     */
    protected function paginateWithTotal(Builder $query, int $total, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', '1'));
        $items = $total > 0 ? $query->forPage($page, $perPage)->get() : new EloquentCollection;

        return (new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]))->withQueryString();
    }

    public function create(#[CurrentUser] User $user, ContentType $contentType): Response
    {
        Gate::authorize('create', [Entry::class, $contentType->name]);

        return Inertia::render('admin/content/entries/edit', [
            ...$this->editorProps($user, $contentType, null),
            'entry' => null,
            'revisions' => [],
        ]);
    }

    public function store(EntryRequest $request, CreateEntry $createEntry, #[CurrentUser] User $user, ContentType $contentType): RedirectResponse
    {
        $entry = $createEntry->handle($user, $contentType->name, $request->entryData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type created.', ['type' => $this->labels->typeLabel($contentType)])]);

        return to_route('admin.entries.edit', ['contentType' => $contentType->name, 'entry' => $entry->id]);
    }

    public function edit(#[CurrentUser] User $user, ContentType $contentType, Entry $entry): Response
    {
        $this->ensureType($contentType, $entry);
        Gate::authorize('update', $entry);

        $entry->load(['author:id,name', 'terms']);

        return Inertia::render('admin/content/entries/edit', [
            ...$this->editorProps($user, $contentType, $entry),
            'entry' => $this->presenter->full($entry),
            'revisions' => $entry->revisions()->with('user:id,name')->get()
                ->map(fn (Revision $revision): array => $this->presenter->revision($revision))
                ->all(),
        ]);
    }

    public function update(EntryRequest $request, UpdateEntry $updateEntry, #[CurrentUser] User $user, ContentType $contentType, Entry $entry): RedirectResponse
    {
        $this->ensureType($contentType, $entry);

        $updateEntry->handle($entry, $request->entryData(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type saved.', ['type' => $this->labels->typeLabel($contentType)])]);

        return to_route('admin.entries.edit', ['contentType' => $contentType->name, 'entry' => $entry->id]);
    }

    public function destroy(TrashEntry $trashEntry, ContentType $contentType, Entry $entry): RedirectResponse
    {
        $this->ensureType($contentType, $entry);
        Gate::authorize('delete', $entry);

        $trashEntry->handle($entry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type moved to the trash.', ['type' => $this->labels->typeLabel($contentType)])]);

        return to_route('admin.entries.index', ['contentType' => $contentType->name]);
    }

    public function restore(RestoreEntry $restoreEntry, ContentType $contentType, Entry $entry): RedirectResponse
    {
        $this->ensureType($contentType, $entry);
        Gate::authorize('restore', $entry);

        $restoreEntry->handle($entry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type restored as a draft.', ['type' => $this->labels->typeLabel($contentType)])]);

        return back();
    }

    // Entry URLs include the type, so /entries/page/{post id} is a 404.
    protected function ensureType(ContentType $contentType, Entry $entry): void
    {
        abort_unless($entry->type === $contentType->name, 404);
    }

    /**
     * Number of entries per status, for the filter tabs.
     *
     * @return array<string, int>
     */
    protected function statusCounts(ContentType $contentType, ?User $ownOrPublished = null): array
    {
        // One query for every status and the trash.
        $trashed = 'case when deleted_at is null then 0 else 1 end';
        $rows = Entry::withTrashed()->ofType($contentType->name)
            ->tap(fn (Builder $query) => $this->limitToVisible($query, $ownOrPublished))
            ->selectRaw("status, {$trashed} as trashed, count(*) as aggregate")
            ->groupBy('status')
            ->groupByRaw($trashed)
            ->toBase()
            ->get();

        $result = ['all' => 0];
        foreach (EntryStatus::editable() as $status) {
            $result[$status->value] = (int) $rows->where('trashed', 0)->where('status', $status->value)->sum('aggregate');
            $result['all'] += $result[$status->value];
        }
        $result['trash'] = (int) $rows->where('trashed', 1)->sum('aggregate');

        return $result;
    }

    /**
     * Without "read private" a user sees only their own entries and the published ones of others.
     *
     * @param  Builder<Entry>  $query
     */
    protected function limitToVisible(Builder $query, ?User $ownOrPublished): void
    {
        if ($ownOrPublished !== null) {
            $query->where(fn (Builder $query) => $query
                ->where('author_id', $ownOrPublished->id)
                ->orWhere(fn (Builder $query) => $query->where('status', EntryStatus::Published)->whereNull('deleted_at')));
        }
    }

    /**
     * Shared props for the create and edit pages.
     *
     * @return array<string, mixed>
     */
    protected function editorProps(User $user, ContentType $contentType, ?Entry $entry): array
    {
        $canPublish = $entry === null ? $user->can('publish', [Entry::class, $contentType->name]) : $user->can('publish', $entry);

        return [
            'contentType' => $this->labels->typeArray($contentType),
            'taxonomies' => $this->taxonomyOptions($contentType),
            'parents' => $contentType->hierarchical ? $this->parentOptions($contentType, $entry) : [],
            'statuses' => array_map(
                fn (EntryStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                $canPublish ? EntryStatus::editable() : [EntryStatus::Draft],
            ),
            'pluginBlocks' => $this->pluginBlocks->toArray(),
            'coreBlocks' => [SectionBlock::editorDefinition()],
            'editor' => app(EditorManifest::class)->for($user, $contentType->name),
            'pluginPanels' => app(EntryPanelRegistry::class)->toArray($contentType->name),
            'fieldGroups' => array_map(
                fn (FieldGroup $group): array => $group->toArray(),
                app(EntryFieldsForm::class)->editorGroups($contentType->name, $user),
            ),
            'pageTemplates' => app(PageTemplates::class)->options($contentType->name),
            'partChoices' => app(TemplatePartChoices::class)->options(),
            'blockPatterns' => app(BlockPatterns::class)->toArray(),
            'can' => [
                'publish' => $canPublish,
                'delete' => $entry !== null && $user->can('delete', $entry),
            ],
        ];
    }

    /**
     * Taxonomies of this type with all their terms.
     *
     * @return list<array<string, mixed>>
     */
    protected function taxonomyOptions(ContentType $contentType): array
    {
        $options = [];

        foreach ($this->taxonomies->forContentType($contentType->name) as $name => $taxonomy) {
            $options[] = [
                ...$this->labels->taxonomyArray($taxonomy),
                'terms' => Term::query()->inTaxonomy($name)->orderBy('name')->get(['id', 'name', 'parent_id'])
                    ->map(fn (Term $term): array => ['id' => $term->id, 'name' => $term->name, 'parent_id' => $term->parent_id])
                    ->all(),
            ];
        }

        return $options;
    }

    /**
     * Possible parents: every entry of the type except this one and its children.
     *
     * @return list<array{id: int, title: string, parent_id: int|null}>
     */
    protected function parentOptions(ContentType $contentType, ?Entry $entry): array
    {
        $all = Entry::query()->ofType($contentType->name)->orderBy('menu_order')->orderBy('title')->get(['id', 'title', 'parent_id']);

        $excluded = [];
        if ($entry !== null) {
            $excluded[$entry->id] = true;
            $changed = true;
            while ($changed) {
                $changed = false;
                foreach ($all as $candidate) {
                    if (! isset($excluded[$candidate->id]) && $candidate->parent_id !== null && isset($excluded[$candidate->parent_id])) {
                        $excluded[$candidate->id] = true;
                        $changed = true;
                    }
                }
            }
        }

        return array_values($all->reject(fn (Entry $candidate): bool => isset($excluded[$candidate->id]))
            ->map(fn (Entry $candidate): array => ['id' => $candidate->id, 'title' => $candidate->title, 'parent_id' => $candidate->parent_id])
            ->all());
    }
}
