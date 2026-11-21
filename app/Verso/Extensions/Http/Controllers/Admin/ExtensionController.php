<?php

namespace App\Verso\Extensions\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Actions\StageRegistryPackage;
use App\Verso\Extensions\Actions\VerifyStagedPackage;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Exceptions\StagedPackageNotFound;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Extensions\Http\Presenters\ExtensionsPageProps;
use App\Verso\Extensions\Verification\VerificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin pages to upload, browse, review, install and remove plugins and themes.
class ExtensionController extends Controller
{
    public function __construct(protected CapabilityRegistry $capabilities) {}

    // The old extensions page is now part of Plugins and themes. Old links still work.
    public function index(Request $request): RedirectResponse
    {
        abort_if($this->allowedKinds($request) === [], 403);

        $tab = $request->query('tab') === 'browse' ? 'browse' : 'add';

        return to_route('admin.plugins.index', ['tab' => $tab]);
    }

    // Turn the "block high risk plugins" setting on or off.
    public function updateSettings(Request $request, VerificationSettings $verification): RedirectResponse
    {
        Gate::authorize(Capability::ManageSettings->value);

        $data = $request->validate(['block_high_risk' => ['required', 'boolean']]);
        $verification->setBlocksHighRisk((bool) $data['block_high_risk']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['block_high_risk'] ? __('High risk plugins are now blocked.') : __('High risk plugins can be installed after a confirmation.')]);

        return back();
    }

    public function upload(Request $request, ExtensionInstaller $installer, VerifyStagedPackage $verify): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_column(ExtensionKind::cases(), 'value'))],
            'package' => ['required', 'file', 'extensions:zip', 'max:'.(int) config('verso.extensions.max_upload_kb')],
        ]);

        $kind = ExtensionKind::from($data['kind']);
        Gate::authorize($kind->capability()->value);

        try {
            $staged = $installer->stage((string) $request->file('package')?->getRealPath(), $kind);
            $verify->handle($staged, $request->user()?->id);
        } catch (InvalidPackage $exception) {
            return back()->withErrors(['package' => implode("\n", $exception->errors)]);
        }

        return to_route('admin.extensions.review', $staged->token);
    }

    public function stageFromRegistry(Request $request, StageRegistryPackage $stage, VerifyStagedPackage $verify, string $kind, string $slug): RedirectResponse
    {
        $extensionKind = $this->kind($kind);

        try {
            $staged = $stage->handle($extensionKind, $slug);
            $verify->handle($staged, $request->user()?->id);
        } catch (ExtensionInstallFailed|InvalidPackage $exception) {
            return $this->failed($exception->getMessage());
        }

        return to_route('admin.extensions.review', $staged->token);
    }

    // The consent screen: package details, the permissions it asks for and the verification report.
    public function review(Request $request, ExtensionInstaller $installer, VerifyStagedPackage $verify, VerificationSettings $settings, string $token): Response|RedirectResponse
    {
        try {
            $staged = $installer->staged($token);
            Gate::authorize($staged->package->kind->capability()->value);
            $verification = $verify->handle($staged, $request->user()?->id);
        } catch (StagedPackageNotFound|InvalidPackage $exception) {
            return $this->failed($exception->getMessage());
        }

        return Inertia::render('admin/extensions/review', [
            'staged' => $staged->toArray(),
            'capabilities' => $this->capabilities->labels(),
            'verification' => $verification->toReportArray(),
            'blockHighRisk' => $settings->blocksHighRisk(),
        ]);
    }

    // Install after consent. High risk packages need the slug typed again, unless they are blocked.
    // Themes with missing views or a broken render are never installed.
    public function install(Request $request, ExtensionInstaller $installer, VerifyStagedPackage $verify, VerificationSettings $settings, string $token): RedirectResponse
    {
        $activate = $request->boolean('activate');

        try {
            $staged = $installer->staged($token);
            Gate::authorize($staged->package->kind->capability()->value);
            $verification = $verify->handle($staged, $request->user()?->id);
            $problem = $verification->installProblem($request->string('confirm_slug')->toString(), $settings->blocksHighRisk());

            if ($problem !== null) {
                return back()->withErrors(['confirm_slug' => $problem]);
            }

            $result = $installer->install($token, $activate);
        } catch (StagedPackageNotFound|InvalidPackage|ExtensionInstallFailed $exception) {
            return $this->failed($exception->getMessage());
        }

        $verification->markInstalled();
        Inertia::flash('toast', ['type' => 'success', 'message' => $result->message()]);

        return to_route($result->kind === ExtensionKind::Plugin ? 'admin.plugins.index' : 'admin.themes.index');
    }

    public function discard(ExtensionInstaller $installer, string $token): RedirectResponse
    {
        abort_if($this->allowedKinds(request()) === [], 403);

        $installer->discard($token);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The upload was discarded.')]);

        return to_route('admin.plugins.index', ['tab' => 'add']);
    }

    // Disable and delete a plugin, or delete a theme that is not in use.
    // "delete_data" (true or false) says whether the tables and settings go too. The default keeps them.
    public function remove(Request $request, ExtensionInstaller $installer, string $kind, string $slug): RedirectResponse
    {
        $extensionKind = $this->kind($kind);
        $deleteData = $request->validate(['delete_data' => ['sometimes', 'boolean']])['delete_data'] ?? false;

        try {
            $installer->remove($extensionKind, $slug, (bool) $deleteData);
        } catch (ExtensionInstallFailed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        $message = match (true) {
            $extensionKind === ExtensionKind::Plugin && $deleteData => __('The plugin ":slug" was removed with its data.', ['slug' => $slug]),
            $extensionKind === ExtensionKind::Plugin => __('The plugin ":slug" was removed. Its data was kept.', ['slug' => $slug]),
            $deleteData => __('The theme ":slug" was removed with its data.', ['slug' => $slug]),
            default => __('The theme ":slug" was removed. Its data was kept.', ['slug' => $slug]),
        };
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $this->afterRemoval($extensionKind, $slug);
    }

    // Back to the page the user came from, or to the list when that page was the detail page of the removed item.
    protected function afterRemoval(ExtensionKind $kind, string $slug): RedirectResponse
    {
        $detail = $kind === ExtensionKind::Plugin ? route('admin.plugins.show', $slug) : route('admin.themes.show', $slug);

        if (rtrim(url()->previous(), '/') !== rtrim($detail, '/')) {
            return back();
        }

        return to_route('admin.plugins.index', $kind === ExtensionKind::Plugin ? [] : ['tab' => 'themes']);
    }

    // Read the kind from the URL and check the user may manage it.
    protected function kind(string $value): ExtensionKind
    {
        $kind = ExtensionKind::tryFrom($value) ?? abort(404);
        Gate::authorize($kind->capability()->value);

        return $kind;
    }

    /**
     * @return list<ExtensionKind>
     */
    protected function allowedKinds(Request $request): array
    {
        return app(ExtensionsPageProps::class)->allowedKinds($request->user());
    }

    protected function failed(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('admin.plugins.index', ['tab' => 'add']);
    }
}
