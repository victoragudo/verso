<?php

namespace App\Verso\Library\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Editor\Blocks\SectionBlock;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\I18n\Translate;
use App\Verso\Library\LibraryStore;
use App\Verso\Library\Models\LibraryItem;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Shortcodes\Usage\UsageIndex;
use App\Verso\Site\Blocks\SiteBlockRegistry;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

// The design library: saved synced blocks and patterns, plus the patterns of the theme and plugins, in one list.
class LibraryController extends Controller
{
    // Name of the search words field, and the most words one item keeps.
    protected const string KEYWORDS = 'keywords';

    protected const int MAX_KEYWORDS = 20;

    public function __construct(protected LibraryStore $store) {}

    public function index(): Response
    {
        $theme = app(ThemeManager::class)->active();

        return Inertia::render('admin/library/index', [
            'items' => LibraryItem::query()->orderBy('kind')->orderBy('title')->orderBy('id')->get()
                ->map(fn (LibraryItem $item): array => [...$item->toEditor(), 'blocks' => count($item->blocks), 'editUrl' => route('admin.library.edit', $item)])->all(),
            'patterns' => array_map(fn (array $pattern): array => [
                'name' => (string) $pattern['name'],
                'title' => (string) $pattern['title'],
                'category' => $pattern['category'],
                'description' => $pattern['description'],
                'keywords' => $pattern['keywords'],
            ], app(BlockPatterns::class)->toArray($theme)),
            'exportUrl' => route('admin.library.export'),
        ]);
    }

    public function edit(Request $request, LibraryItem $item, UsageIndex $usage): Response
    {
        return Inertia::render('admin/library/edit', [
            'item' => $item->toEditor(),
            'siteBlocks' => [SectionBlock::editorDefinition(), ...app(SiteBlockRegistry::class)->toArray(), ...app(PluginBlockRegistry::class)->toArray()],
            'editor' => app(EditorManifest::class)->for($request->user()),
            'usedIn' => $item->kind === LibraryItem::SYNCED ? $usage->usedIn('synced', [(string) $item->id], (int) config('verso.library.used_in_limit')) : [],
        ]);
    }

    // Saves a new item. The editor asks with JSON and gets the item back; forms go to the new item.
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $item = $this->store->save(null, $this->validated($request, null), $request->user());

        return $request->wantsJson()
            ? response()->json(['item' => $item->toEditor()], 201)
            : to_route('admin.library.edit', $item);
    }

    public function update(Request $request, LibraryItem $item): JsonResponse|RedirectResponse
    {
        $item = $this->store->save($item, $this->validated($request, $item), $request->user());

        if ($request->wantsJson()) {
            return response()->json(['item' => $item->toEditor()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('“:title” saved. Every place that uses it shows the change.', ['title' => $item->title])]);

        return back();
    }

    public function destroy(LibraryItem $item): RedirectResponse
    {
        $this->store->delete($item);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('“:title” was deleted from the library.', ['title' => $item->title])]);

        return to_route('admin.library.index');
    }

    public function export(Request $request): SymfonyResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->query('ids', []))));
        $json = (string) json_encode($this->store->export($ids), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($json, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="verso-library.json"']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.(int) config('verso.library.import_max_kb')]]);
        $data = json_decode((string) $request->file('file')?->get(), true);

        try {
            $count = $this->store->import(is_array($data) ? $data : [], $request->user() ?? abort(403));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('{count, plural, one {# item added to the library.} other {# items added to the library.}}', ['count' => $count])]);

        return to_route('admin.library.index');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?LibraryItem $item): array
    {
        return $request->validate([
            'kind' => [$item === null ? 'required' : 'sometimes', Rule::in(LibraryItem::KINDS)],
            'title' => [$item === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            self::KEYWORDS => ['sometimes', 'array', 'max:'.self::MAX_KEYWORDS],
            self::KEYWORDS.'.*' => ['string', 'max:40'],
            'blocks' => [$item === null ? 'required' : 'sometimes', 'array', 'min:1', new ValidBlocks($request->user(), stored: $item->blocks ?? [])],
        ]);
    }
}
