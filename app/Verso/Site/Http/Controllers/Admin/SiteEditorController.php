<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\Blocks\SectionBlock;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Editor\Builder\ThemeEditorLimits;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Site\Blocks\SiteBlockRegistry;
use App\Verso\Site\Models\SitePart;
use App\Verso\Site\Parts\SiteCustomization;
use App\Verso\Site\Parts\SitePartDefaults;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Site\Parts\SiteTemplates;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Http\Controllers\HomeController;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Templates\TemplateParts;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

// The Site Editor: edit the header, footer and other parts of the active theme with blocks.
class SiteEditorController extends Controller
{
    public function __construct(
        protected ThemeManager $themes,
        protected TemplateParts $parts,
        protected SitePartStore $store,
        protected SitePartDefaults $defaults,
    ) {}

    public function index(): Response
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $edited = $this->store->forTheme($theme->slug);

        return Inertia::render('admin/site-editor/index', [
            'theme' => ['name' => $theme->name, 'slug' => $theme->slug],
            'parts' => array_map(fn (string $key): array => $this->summary($key, $edited[$key] ?? null), $this->parts->available($theme)),
            'styles' => $this->styles($theme),
            'globalStyles' => $this->globalStyles($theme),
            'previewUrl' => route('admin.site-editor.preview'),
        ]);
    }

    public function edit(Request $request, string $part): Response
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $key = $this->partKey($theme, $part);
        $sitePart = $this->store->find($theme->slug, $key);

        return Inertia::render('admin/site-editor/edit', [
            'theme' => ['name' => $theme->name, 'slug' => $theme->slug],
            'part' => $this->summary($key, $sitePart),
            'blocks' => $sitePart === null ? $this->defaults->blocks($theme, $key) : $sitePart->blocks,
            'siteBlocks' => [SectionBlock::editorDefinition(), ...app(SiteBlockRegistry::class)->toArray(), ...app(PluginBlockRegistry::class)->toArray()],
            'patterns' => app(BlockPatterns::class)->toArray($theme),
            'editor' => app(EditorManifest::class)->for($request->user()),
            'previewUrl' => route('admin.site-editor.preview', ['styles' => $this->stylesShowing($theme, $key)]),
            'shownWith' => $this->shownWith($theme, $key),
            'previewWidths' => config('verso.site_parts.preview_widths'),
            'previewDesktopMinWidth' => (int) config('verso.site_parts.preview_desktop_min_width'),
            'autosaveDelay' => (int) config('verso.site_parts.autosave_delay'),
            'undoSteps' => (int) config('verso.site_parts.undo_steps'),
        ]);
    }

    // Saves the working copy. Visitors keep seeing the published version.
    public function update(Request $request, string $part): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $key = $this->partKey($theme, $part);
        $this->store->saveDraft($theme->slug, $key, $this->validatedBlocks($request, $theme, $key), $request->user()?->id);

        return back();
    }

    public function publish(Request $request, string $part): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $key = $this->partKey($theme, $part);
        $this->store->publish($theme->slug, $key, $this->validatedBlocks($request, $theme, $key), $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':part published. Visitors see it now.', ['part' => $this->defaults->label($key, $this->parts->available($theme))])]);

        return back();
    }

    // "Restore original": forget the edits so the theme version is used again.
    public function destroy(string $part): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $key = $this->partKey($theme, $part);
        $this->store->restoreDefault($theme->slug, $key);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':part is back to the theme original.', ['part' => $this->defaults->label($key, $this->parts->available($theme))])]);

        return to_route('admin.site-editor.edit', $key);
    }

    // Saves the style (variant) of a part, like the "Centered" header.
    public function chooseStyle(Request $request, PartVariants $variants): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $data = $request->validate(['part' => ['required', 'string', 'max:100'], 'style' => ['required', 'string', 'max:100']]);
        $theme = $this->themes->active();

        if (! $variants->choose($theme, $data['part'], $data['style'])) {
            return back()->withErrors(['style' => __('This theme does not offer that style.')]);
        }

        $definition = $variants->definitions($theme)[$data['part']];
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':part style changed to :style.', ['part' => $definition['label'], 'style' => $definition['variants'][$data['style']]['label']])]);

        return back();
    }

    // Saves colors, fonts and the other design tokens of the active theme.
    public function saveGlobalStyles(Request $request, GlobalStyles $globalStyles): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $data = $request->validate(['values' => ['required', 'array']]);
        $theme = $this->themes->active();
        $tokens = $globalStyles->tokens($theme);
        $rejected = $globalStyles->save($theme, $data['values']);

        if ($rejected !== []) {
            return back()->withErrors(array_combine(
                array_map(fn (string $id): string => "values.{$id}", $rejected),
                array_map(fn (string $id): string => __(':setting: this value is not allowed.', ['setting' => $tokens[$id]['label']]), $rejected),
            ));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Colors and fonts saved.')]);

        return back();
    }

    // Which template each kind of page uses, and the header and footer styles.
    public function templates(SiteTemplates $templates): Response
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $this->themes->registerViews();

        return Inertia::render('admin/site-editor/templates', [
            'theme' => ['name' => $theme->name, 'slug' => $theme->slug],
            ...$templates->overview($theme),
        ]);
    }

    // Downloads the whole customization of the active theme as JSON.
    public function export(SiteCustomization $customization): SymfonyResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $this->themes->active();
        $json = (string) json_encode($customization->export($theme), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$theme->slug.'-customization.json"',
        ]);
    }

    // Applies a JSON file made by the export to the active theme.
    public function import(Request $request, SiteCustomization $customization): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $request->validate(['file' => ['required', 'file', 'max:'.(int) config('verso.site_parts.import_max_kb')]]);
        $data = json_decode((string) $request->file('file')?->get(), true);

        try {
            $result = $customization->import($this->themes->active(), is_array($data) ? $data : [], $request->user() ?? abort(403), (int) $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu('Imported {parts, plural, one {# part} other {# parts}} and {menus, plural, one {# menu} other {# menus}}.', ['parts' => $result['parts'], 'menus' => $result['menus']])]);

        return to_route('admin.site-editor');
    }

    // The home page with the saved drafts, for the preview frame.
    // ?styles[header]=centered tries a style, ?tokens[color.accent]=%23123456 tries a color and ?logo=12 tries a logo (0 for none).
    public function preview(Request $request, PartVariants $variants, GlobalStyles $globalStyles, ThemeCustomizer $customizer): SymfonyResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $slug = $this->themes->active()->slug;
        $this->themes->usePreview($slug);
        $this->store->useDrafts($slug);

        foreach ((array) $request->query('styles', []) as $name => $style) {
            if (is_string($name) && is_string($style)) {
                $variants->preview($name, $style);
            }
        }

        $theme = $this->themes->active();
        $globalStyles->preview($theme, (array) $request->query('tokens', []));
        $logoField = $customizer->logoField($theme);

        if ($logoField !== null && $request->query->has('logo')) {
            $customizer->previewValue($theme, $logoField, $request->integer('logo') ?: null);
        }

        /** @var SymfonyResponse $response */
        $response = app()->call([app(HomeController::class), '__invoke']);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * The part style that uses this part, so the preview shows it, like ["header" => "centered"].
     *
     * @return array<string, string>
     */
    protected function stylesShowing(Theme $theme, string $key): array
    {
        foreach (app(PartVariants::class)->definitions($theme) as $name => $definition) {
            foreach ($definition['variants'] as $variant) {
                if ($variant['part'] === $key) {
                    return [$name => $variant['key']];
                }
            }
        }

        return [];
    }

    /**
     * When visitors see another style of this part, its label and the label of this part's style. Null when this part is in use.
     *
     * @return array{inUse: string, thisStyle: string}|null
     */
    protected function shownWith(Theme $theme, string $key): ?array
    {
        $variants = app(PartVariants::class);

        foreach ($variants->definitions($theme) as $name => $definition) {
            foreach ($definition['variants'] as $variant) {
                $chosen = (string) $variants->chosen($theme, $name);

                if ($variant['part'] === $key && $chosen !== $variant['key']) {
                    return ['inUse' => $definition['variants'][$chosen]['label'], 'thisStyle' => $variant['label']];
                }
            }
        }

        return null;
    }

    /**
     * Tokens grouped for the Styles panel, with their values and the font list.
     *
     * @return array{groups: list<array{name: string, tokens: list<array<string, mixed>>}>, values: array<string, string>, fonts: list<array{value: string, label: string, stack: string}>, units: list<string>|null}
     */
    protected function globalStyles(Theme $theme): array
    {
        $globalStyles = app(GlobalStyles::class);
        $groups = [];

        foreach ($globalStyles->tokens($theme) as $id => $token) {
            $groups[$token['group']][] = [
                'id' => $id, 'kind' => $token['kind'], 'label' => $token['label'], 'help' => $token['help'], 'default' => $token['default'],
                'value' => $token['kind'] === 'length' ? $globalStyles->rule()->toArray() : null,
            ];
        }

        return [
            'groups' => array_map(fn (string $name, array $tokens): array => ['name' => $name, 'tokens' => $tokens], array_keys($groups), array_values($groups)),
            'values' => $globalStyles->values($theme),
            'fonts' => $globalStyles->fonts(),
            'units' => app(ThemeEditorLimits::class)->units(),
        ];
    }

    /**
     * Parts with styles for the style picker.
     *
     * @return list<array{name: string, label: string, chosen: string, variants: list<array{key: string, label: string, description: string, part: string}>}>
     */
    protected function styles(Theme $theme): array
    {
        $variants = app(PartVariants::class);
        $styles = [];

        foreach ($variants->definitions($theme) as $name => $definition) {
            $styles[] = [
                'name' => $name,
                'label' => $definition['label'],
                'chosen' => (string) $variants->chosen($theme, $name),
                'variants' => array_values($definition['variants']),
            ];
        }

        return $styles;
    }

    /**
     * @return array{key: string, label: string, status: string, editedAt: string|null, revisions: int}
     */
    protected function summary(string $key, ?SitePart $sitePart): array
    {
        $status = match (true) {
            $sitePart === null => 'original',
            $sitePart->hasUnpublishedChanges() => 'unpublished',
            default => 'published',
        };

        return [
            'key' => $key,
            'label' => $this->defaults->label($key, $this->parts->available($this->themes->active())),
            'status' => $status,
            'editedAt' => $sitePart?->updated_at?->toIso8601String(),
            'revisions' => (int) ($sitePart->revisions_count ?? ($sitePart?->revisions()->count() ?? 0)),
        ];
    }

    // Only parts the active theme ships can be edited.
    protected function partKey(Theme $theme, string $part): string
    {
        abort_unless(in_array($part, $this->parts->available($theme), true), 404);

        return $part;
    }

    /**
     * @return array<int, mixed>
     */
    protected function validatedBlocks(Request $request, Theme $theme, string $key): array
    {
        $stored = $this->store->find($theme->slug, $key)->blocks ?? [];
        $data = $request->validate(['blocks' => ['present', 'array', new ValidBlocks($request->user(), stored: $stored)]]);

        return array_values((array) $data['blocks']);
    }
}
