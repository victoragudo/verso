<?php

namespace App\Verso\Layouts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\SectionBlock;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Layouts\Conditions\ConditionRegistry;
use App\Verso\Layouts\LayoutKind;
use App\Verso\Layouts\LayoutPreview;
use App\Verso\Layouts\LayoutSettings;
use App\Verso\Layouts\LayoutStarters;
use App\Verso\Layouts\LayoutStore;
use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Layouts\Models\SiteLayoutRevision;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Site\Blocks\SiteBlockRegistry;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

// The theme builder in the Site Editor: templates, headers, footers and popups built with blocks.
class LayoutController extends Controller
{
    public function __construct(
        protected LayoutStore $store,
        protected ConditionRegistry $conditions,
    ) {}

    public function index(Request $request): Response
    {
        $kinds = $this->kinds($request->user());

        return Inertia::render('admin/site-editor/layouts/index', [
            'kinds' => array_map(fn (LayoutKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], $kinds),
            'layouts' => SiteLayout::query()
                ->whereIn('kind', array_map(fn (LayoutKind $kind): string => $kind->value, $kinds))
                ->orderBy('kind')->orderByDesc('priority')->orderBy('name')->orderBy('id')
                ->get()
                ->map(fn (SiteLayout $layout): array => $this->summary($layout))
                ->all(),
            'conditions' => $this->conditions->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_map(fn (LayoutKind $kind): string => $kind->value, $this->kinds($request->user())))],
            'name' => ['required', 'string', 'max:120'],
        ]);
        $kind = LayoutKind::from($data['kind']);
        $layout = SiteLayout::query()->create([
            'kind' => $kind,
            'name' => $data['name'],
            'blocks' => LayoutStarters::blocks($kind),
            'conditions' => LayoutStarters::conditions($kind),
            'settings' => LayoutSettings::clean($kind, []),
            'status' => SiteLayout::DRAFT,
            'user_id' => $request->user()?->id,
        ]);

        return to_route('admin.theme-builder.edit', $layout);
    }

    public function edit(Request $request, SiteLayout $layout, LayoutPreview $preview): Response
    {
        $this->authorizeKind($layout);
        $theme = app(ThemeManager::class)->active();

        return Inertia::render('admin/site-editor/layouts/edit', [
            'layout' => [...$this->summary($layout), 'blocks' => $layout->blocks, 'settings' => LayoutSettings::clean($layout->kind, $layout->settings)],
            'siteBlocks' => [SectionBlock::editorDefinition(), ...app(SiteBlockRegistry::class)->toArray(), ...app(PluginBlockRegistry::class)->toArray()],
            'patterns' => app(BlockPatterns::class)->toArray($theme),
            'editor' => app(EditorManifest::class)->for($request->user()),
            'conditions' => $this->conditions->toArray(),
            'previewTargets' => $preview->targets($layout, $request->user()),
            'previewDesktopMinWidth' => (int) config('verso.site_parts.preview_desktop_min_width'),
            'previewUrl' => route('admin.theme-builder.preview', $layout),
            'previewWidths' => config('verso.site_parts.preview_widths'),
            'autosaveDelay' => (int) config('verso.site_parts.autosave_delay'),
            'revisions' => $layout->revisions()->with('user:id,name')->limit((int) config('verso.layouts.max_revisions'))->get()
                ->map(fn (SiteLayoutRevision $revision): array => ['id' => $revision->id, 'createdAt' => $revision->created_at?->toIso8601String(), 'author' => $revision->user?->name])->all(),
        ]);
    }

    // Saves the working copy. Visitors keep seeing the published version.
    public function update(Request $request, SiteLayout $layout): RedirectResponse
    {
        $this->authorizeKind($layout);
        $this->store->saveDraft($layout, $this->validated($request, $layout), $request->user()?->id);

        return back();
    }

    public function publish(Request $request, SiteLayout $layout): RedirectResponse
    {
        $this->authorizeKind($layout);
        $this->store->publish($layout, $this->validated($request, $layout), $request->user()?->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is live. Visitors see it now.', ['name' => $layout->name])]);

        return back();
    }

    // Turns a layout off (visitors stop seeing it) or on again.
    public function toggle(Request $request, SiteLayout $layout): RedirectResponse
    {
        $this->authorizeKind($layout);
        $on = $request->boolean('on');
        $this->store->switch($layout, $on);
        Inertia::flash('toast', ['type' => 'success', 'message' => $on ? __(':name is on.', ['name' => $layout->name]) : __(':name is off. Visitors no longer see it.', ['name' => $layout->name])]);

        return back();
    }

    public function destroy(SiteLayout $layout): RedirectResponse
    {
        $this->authorizeKind($layout);
        $this->store->delete($layout);
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deleted.', ['name' => $layout->name])]);

        return to_route('admin.theme-builder.index');
    }

    // Puts an older version back into the working copy. It reaches visitors after "Publish".
    public function restore(Request $request, SiteLayout $layout, SiteLayoutRevision $revision): RedirectResponse
    {
        $this->authorizeKind($layout);
        abort_unless($revision->site_layout_id === $layout->id, 404);

        $this->store->saveDraft($layout, ['blocks' => $revision->blocks, 'conditions' => $revision->conditions, 'settings' => $revision->settings], $request->user()?->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The older version is back in the editor. Publish to show it to visitors.')]);

        return back();
    }

    // A public page drawn with the working copy of the layout, for the preview frame.
    public function preview(Request $request, SiteLayout $layout, LayoutPreview $preview): SymfonyResponse
    {
        $this->authorizeKind($layout);
        $this->store->preview($layout, $layout->blocks, $layout->settings);
        app(BlockRenderer::class)->showMarkers(false);

        $response = $preview->render((string) $request->query('target', 'home'));
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** @return list<LayoutKind> */
    protected function kinds(?User $user): array
    {
        return array_values(array_filter(LayoutKind::cases(), fn (LayoutKind $kind): bool => $user?->can($kind->capability()->value) ?? false));
    }

    protected function authorizeKind(SiteLayout $layout): void
    {
        Gate::authorize($layout->kind->capability()->value);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, SiteLayout $layout): array
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'priority' => ['sometimes', 'integer', 'between:-100,100'],
            'blocks' => ['sometimes', 'present', 'array', new ValidBlocks($request->user(), stored: $layout->blocks)],
            'conditions' => ['sometimes', 'array', 'max:'.(int) config('verso.layouts.max_conditions')],
            'settings' => ['sometimes', 'array'],
        ]);

        $errors = $this->conditions->errors((array) ($data['conditions'] ?? []));

        if ($errors !== []) {
            throw ValidationException::withMessages(['conditions' => $errors[0]]);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function summary(SiteLayout $layout): array
    {
        return [
            'id' => $layout->id,
            'kind' => $layout->kind->value,
            'kindLabel' => $layout->kind->label(),
            'name' => $layout->name,
            'status' => $layout->status,
            'live' => $layout->isLive(),
            'priority' => $layout->priority,
            'conditions' => $layout->conditions,
            'updatedAt' => $layout->updated_at?->toIso8601String(),
            'editUrl' => route('admin.theme-builder.edit', $layout),
        ];
    }
}
