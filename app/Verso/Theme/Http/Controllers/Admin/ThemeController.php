<?php

namespace App\Verso\Theme\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Actions\VerifyInstalledTheme;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\I18n\Translate;
use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Http\Controllers\HomeController;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Templates\BlockPatterns;
use App\Verso\Theme\Templates\PageTemplates;
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

// Admin pages to see the installed themes, check one and choose the active one.
class ThemeController extends Controller
{
    public function __construct(protected ThemeText $text) {}

    public function index(ThemeManager $themes, PageCache $cache, ExtensionInstaller $installer, PageTemplates $templates, BlockPatterns $patterns, TemplateParts $parts): Response
    {
        Gate::authorize(Capability::ManageThemes->value);

        $active = $themes->active()->slug;
        $risks = PluginVerification::latestRisks(ExtensionKind::Theme);

        return Inertia::render('admin/theme/index', [
            'themes' => array_values(array_map(fn (Theme $theme): array => [
                ...$this->themeArray($theme),
                'parentName' => $this->parentName($themes, $theme),
                'missingParent' => $themes->missingParent($theme),
                'active' => $theme->slug === $active,
                'removable' => $installer->removalProblem(ExtensionKind::Theme, $theme->slug) === null,
                'customizable' => $theme->settings !== [],
                'templates' => count($templates->all($theme)),
                'patterns' => count($patterns->all($theme)),
                'parts' => count($parts->available($theme)),
                'risk' => $risks[$theme->slug] ?? null,
            ], $themes->all())),
            'pageCache' => [
                'enabled' => $cache->enabled(),
                'ttl' => (int) config('verso.page_cache.ttl'),
            ],
        ]);
    }

    // One theme with its details and its newest verification report.
    public function show(ThemeManager $themes, ExtensionInstaller $installer, string $theme): Response
    {
        Gate::authorize(Capability::ManageThemes->value);

        $found = $themes->find($theme) ?? abort(404);

        return Inertia::render('admin/theme/show', [
            'theme' => [
                ...$this->themeArray($found),
                'parentName' => $this->parentName($themes, $found),
                'missingParent' => $themes->missingParent($found),
                'missingViews' => $themes->missingRequiredViews($found),
                'active' => $themes->active()->slug === $found->slug,
                'removable' => $installer->removalProblem(ExtensionKind::Theme, $found->slug) === null,
                'customizable' => $found->settings !== [],
            ],
            'verification' => PluginVerification::latestFor($found->slug, ExtensionKind::Theme)?->toReportArray(),
            'versoVersion' => (string) config('verso.version'),
        ]);
    }

    // Run the checks again on the installed files.
    public function verify(Request $request, ThemeManager $themes, VerifyInstalledTheme $verify, string $theme): RedirectResponse
    {
        Gate::authorize(Capability::ManageThemes->value);

        $found = $themes->find($theme) ?? abort(404);
        $verification = $verify->handle($found, $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu(
            'Checks finished: {risk, select, low {low risk} medium {medium risk} other {high risk}}.',
            ['risk' => $verification->risk->value],
        )]);

        return to_route('admin.themes.show', $found->slug);
    }

    public function activate(ThemeManager $themes, ActivateTheme $activateTheme, string $theme): RedirectResponse
    {
        Gate::authorize(Capability::ManageThemes->value);

        abort_unless($themes->exists($theme), 404);

        try {
            $activated = $activateTheme->handle($theme);
        } catch (InvalidArgumentException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':theme is now the active theme.', ['theme' => $this->text->text($activated, $activated->name)])]);

        return back();
    }

    public function clearCache(PageCache $cache): RedirectResponse
    {
        Gate::authorize(Capability::ManageThemes->value);

        $cache->flush();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page cache cleared.')]);

        return back();
    }

    // The customizer: a form built from the theme.json settings schema, with a live preview.
    public function customize(ThemeManager $themes, ThemeCustomizer $customizer, string $theme): Response
    {
        Gate::authorize(Capability::ManageThemes->value);

        $found = $themes->find($theme) ?? abort(404);
        $schema = $customizer->schema($found);
        $values = $customizer->values($found);
        $logo = $customizer->logo($found);

        return Inertia::render('admin/theme/customize', [
            'theme' => [...$this->themeArray($found), 'active' => $themes->active()->slug === $found->slug],
            'fields' => array_map(fn (array $field): array => [
                ...$field,
                'label' => $this->text->text($found, (string) $field['label']),
                'help' => $this->text->text($found, (string) $field['help']),
            ], $schema->toArray()),
            'schemaErrors' => $schema->errors,
            'values' => $values,
            'media' => $logo === null ? [] : [$logo->id => ['id' => $logo->id, 'url' => $logo->url(), 'filename' => $logo->filename]],
            'fonts' => ThemeCustomizer::fontLabels(),
        ]);
    }

    public function saveCustomization(Request $request, ThemeManager $themes, ThemeCustomizer $customizer, string $theme): RedirectResponse
    {
        Gate::authorize(Capability::ManageThemes->value);

        $found = $themes->find($theme) ?? abort(404);
        $data = $request->validate($customizer->schema($found)->rules());

        $customizer->save($found, (array) ($data['values'] ?? []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':theme settings saved.', ['theme' => $this->text->text($found, $found->name)])]);

        return back();
    }

    // Render the home page with the theme and the draft values from the query string.
    public function preview(Request $request, ThemeManager $themes, ThemeCustomizer $customizer, string $theme): SymfonyResponse
    {
        Gate::authorize(Capability::ManageThemes->value);

        $found = $themes->find($theme) ?? abort(404);
        $values = $request->query('values', []);

        $customizer->usePreview($found, is_array($values) ? $values : []);
        $themes->usePreview($found->slug);

        /** @var SymfonyResponse $response */
        $response = app()->call([app(HomeController::class), '__invoke']);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @return array{slug: string, name: string, version: string, description: string, author: string, parent: string|null}
     */
    protected function themeArray(Theme $theme): array
    {
        return [
            ...$theme->toArray(),
            'name' => $this->text->text($theme, $theme->name),
            'description' => $this->text->text($theme, $theme->description),
        ];
    }

    protected function parentName(ThemeManager $themes, Theme $theme): ?string
    {
        $parent = $theme->parent === null ? null : $themes->find($theme->parent);

        return $parent === null ? null : $this->text->text($parent, $parent->name);
    }
}
