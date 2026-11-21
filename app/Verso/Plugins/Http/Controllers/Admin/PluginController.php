<?php

namespace App\Verso\Plugins\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Actions\VerifyInstalledPlugin;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Extensions\Http\Presenters\ExtensionsPageProps;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\Actions\DisablePlugin;
use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\Admin\PluginNavPlacement;
use App\Verso\Plugins\Exceptions\PluginCannotBeEnabled;
use App\Verso\Plugins\Exceptions\PluginNotFound;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Support\PluginTranslations;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Admin pages to list plugins, show one with its verification report, and turn them on or off.
class PluginController extends Controller
{
    // Tabs of the Plugins and themes page.
    public const TABS = ['plugins', 'themes', 'add', 'browse'];

    public function __construct(protected CapabilityRegistry $capabilities) {}

    // One page for plugins and themes: installed plugins, installed themes, upload and the registry.
    public function index(Request $request, PluginManager $plugins, ExtensionInstaller $installer, ExtensionsPageProps $extensions, ThemeManager $themes, PluginTranslations $texts): Response
    {
        $user = $request->user();
        $canThemes = (bool) $user?->can(Capability::ManageThemes->value);
        $canInstall = (bool) $user?->can(Capability::InstallPlugins->value) || $canThemes;
        $tabs = array_values(array_filter(self::TABS, fn (string $tab): bool => match ($tab) {
            'themes' => $canThemes,
            'add', 'browse' => $canInstall,
            default => true,
        }));
        $tab = in_array($request->query('tab'), $tabs, true) ? (string) $request->query('tab') : 'plugins';
        $risks = PluginVerification::latestRisks();

        return Inertia::render('admin/plugins/index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'plugins' => array_map(fn (Plugin $plugin): array => [
                ...$this->pluginProps($plugin, $texts),
                'removable' => $installer->removalProblem(ExtensionKind::Plugin, $plugin->slug) === null,
                'risk' => $risks[$plugin->slug] ?? null,
                'location' => $this->location($plugin),
            ], $plugins->all()),
            'themes' => $tab === 'themes' ? $this->themes($themes, $installer) : [],
            'add' => in_array($tab, ['add', 'browse'], true) ? $extensions->add($user) : null,
            'registry' => $tab === 'browse' ? $extensions->browse($user) : null,
            'capabilities' => $this->capabilities->labels(),
            'versoVersion' => (string) config('verso.version'),
        ]);
    }

    // One plugin with its details and its newest verification report.
    public function show(PluginManager $plugins, ExtensionInstaller $installer, PluginTranslations $texts, string $plugin): Response
    {
        $found = $plugins->find($plugin) ?? abort(404);

        return Inertia::render('admin/plugins/show', [
            'plugin' => [
                ...$this->pluginProps($found, $texts),
                'removable' => $installer->removalProblem(ExtensionKind::Plugin, $found->slug) === null,
                'network' => $found->manifest !== null ? $found->manifest->network : [],
            ],
            'verification' => PluginVerification::latestFor($found->slug)?->toReportArray(),
            'capabilities' => $this->capabilities->labels(),
            'versoVersion' => (string) config('verso.version'),
        ]);
    }

    // Run the checks again on the installed files.
    public function verify(Request $request, VerifyInstalledPlugin $verify, string $plugin): RedirectResponse
    {
        try {
            $verification = $verify->handle($plugin, $request->user()?->id);
        } catch (PluginNotFound) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu(
            'Checks finished: {risk, select, low {low risk} medium {medium risk} other {high risk}}.',
            ['risk' => $verification->risk->value],
        )]);

        return to_route('admin.plugins.show', $plugin);
    }

    public function enable(EnablePlugin $enablePlugin, PluginNavPlacement $placement, PluginTranslations $texts, string $plugin): RedirectResponse
    {
        try {
            $enabled = $enablePlugin->handle($plugin);
        } catch (PluginNotFound) {
            abort(404);
        } catch (PluginCannotBeEnabled $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $name = $texts->name($enabled);
        $location = $placement->location($enabled);

        // Tell the user where to find the plugin. The sidebar shows the item for a moment.
        Inertia::flash('toast', $location === null
            ? ['type' => 'success', 'message' => __(':name enabled.', ['name' => $name])]
            : [
                'type' => 'success',
                'message' => __(':name is ready. You will find it in :place.', ['name' => $name, 'place' => implode(' > ', $location['path'])]),
                'action' => ['label' => __('Open'), 'navKey' => $location['key']],
            ]);

        if ($location !== null) {
            Inertia::flash('navHighlight', $location['key']);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginProps(Plugin $plugin, PluginTranslations $texts): array
    {
        return [
            ...$plugin->toArray(),
            'name' => $texts->name($plugin),
            'description' => $texts->description($plugin),
        ];
    }

    /**
     * Where an enabled plugin is in the sidebar, like "Tools > Shop", with a link when the page exists.
     *
     * @return array{path: list<string>, key: string, href: string|null}|null
     */
    protected function location(Plugin $plugin): ?array
    {
        $location = app(PluginNavPlacement::class)->location($plugin);

        if ($location === null) {
            return null;
        }

        $row = $plugin->enabled ? collect(AdminNav::forUser(request()->user()))->firstWhere('key', $location['key']) : null;

        return [...$location, 'href' => is_array($row) ? $row['href'] : null];
    }

    /**
     * Installed themes for the "Installed themes" tab.
     *
     * @return list<array<string, mixed>>
     */
    protected function themes(ThemeManager $themes, ExtensionInstaller $installer): array
    {
        $active = $themes->active()->slug;
        $risks = PluginVerification::latestRisks(ExtensionKind::Theme);

        return array_values(array_map(fn (Theme $theme): array => [
            'slug' => $theme->slug,
            'name' => $theme->name,
            'version' => $theme->version,
            'description' => $theme->description,
            'parentName' => $theme->parent === null ? null : $themes->find($theme->parent)?->name,
            'active' => $theme->slug === $active,
            'removable' => $installer->removalProblem(ExtensionKind::Theme, $theme->slug) === null,
            'risk' => $risks[$theme->slug] ?? null,
        ], $themes->all()));
    }

    public function disable(DisablePlugin $disablePlugin, PluginTranslations $texts, string $plugin): RedirectResponse
    {
        try {
            $disabled = $disablePlugin->handle($plugin);
        } catch (PluginNotFound) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name disabled.', ['name' => $texts->name($disabled)])]);

        return back();
    }
}
