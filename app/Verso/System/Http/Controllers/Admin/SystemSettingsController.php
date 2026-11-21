<?php

namespace App\Verso\System\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Access\Support\AccessAudit;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\SiteTime;
use App\Verso\I18n\Translate;
use App\Verso\System\Actions\SaveSystemSettings;
use App\Verso\System\Config\ConfigGroup;
use App\Verso\System\Config\ConfigOverlay;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Http\Requests\SystemSettingsRequest;
use App\Verso\System\Models\ConfigChange;
use App\Verso\System\Server\IniTarget;
use App\Verso\System\Server\ManagedIniFile;
use App\Verso\System\Server\PhpIni;
use App\Verso\System\Server\ServerAdvisor;
use App\Verso\System\Server\UploadLimits;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

// The System settings area: every registered setting in groups, server limits and the audit log.
class SystemSettingsController extends Controller
{
    public function index(Request $request, ConfigRegistry $registry, ConfigOverlay $overlay, Settings $settings, UploadLimits $uploads, ServerAdvisor $advisor, ManagedIniFile $iniFile, PhpIni $ini, SiteTime $time): Response
    {
        Gate::authorize(Capability::ManageSystem->value);

        $user = $request->user();
        $parameters = array_values(array_filter($registry->all(), fn (ConfigParameter $parameter): bool => (bool) $user?->can($parameter->capability->value)));
        $problems = $overlay->problems();
        $recommended = $advisor->recommended();
        $target = $advisor->iniTarget();
        $groups = $registry->groups();
        $plugins = $this->groupPlugins($groups);

        return Inertia::render('admin/system/settings', [
            'groups' => array_map(fn (ConfigGroup $group): array => $group->toArray(), $groups),
            'parameters' => array_map(fn (ConfigParameter $parameter): array => [
                ...$parameter->toArray($plugins[$parameter->group] ?? null),
                'value' => config($parameter->key),
                'default' => $overlay->defaultValue($parameter),
                'changed' => ! $overlay->isSetByEnvironment($parameter) && $settings->has($parameter->settingKey()) && ! isset($problems[$parameter->key]),
                'envLocked' => $overlay->isSetByEnvironment($parameter),
                'problem' => isset($problems[$parameter->key]) ? ($parameter->problem($settings->get($parameter->settingKey())) ?? $problems[$parameter->key]) : null,
                'upload' => $parameter->isUploadLimit() ? $uploads->effective((int) config($parameter->key))->toArray() : null,
            ], $parameters),
            'server' => [
                'sapi' => $ini->sapi(),
                'webServer' => $advisor->webServer()->label(),
                'phpUpload' => [
                    'upload_max_filesize' => $ini->get('upload_max_filesize'),
                    'post_max_size' => $ini->get('post_max_size'),
                ],
                'target' => $target === null ? null : [
                    'key' => $target->value,
                    'label' => $target->label(),
                    'file' => 'public/'.$target->fileName($ini->userIniFilename()),
                    'writable' => $iniFile->writable($target),
                    'preview' => $recommended === [] ? null : $iniFile->block($target, $recommended),
                ],
                'cacheTtl' => (int) $ini->get('user_ini.cache_ttl'),
                'recommended' => array_map(fn (string $name, string $value): array => ['name' => $name, 'current' => $ini->get($name), 'value' => $value, 'scope' => ServerAdvisor::scope($ini->access($name))], array_keys($recommended), $recommended),
                'snippets' => $advisor->snippets($recommended),
            ],
            'audit' => ConfigChange::query()->with('user:id,name')->whereNotIn('action', AccessAudit::ACTIONS)->latest('id')->limit(max(1, (int) config('verso.system.audit_limit')))->get()
                ->map(fn (ConfigChange $change): array => [
                    'id' => $change->id,
                    'action' => $change->action,
                    'key' => $change->key,
                    'label' => $change->key === null ? null : $this->settingLabel($registry, $plugins, $change->key),
                    'old' => $change->old_value,
                    'new' => $change->new_value,
                    'details' => $change->details,
                    'user' => $change->user?->name,
                    'at' => $time->iso($change->created_at),
                ])->all(),
            'healthUrl' => route('admin.system.health.index'),
        ]);
    }

    public function update(SystemSettingsRequest $request, #[CurrentUser] User $user, SaveSystemSettings $save): RedirectResponse
    {
        $result = $save->handle($user, $request->values());
        $message = Translate::icu('{count, plural, =0 {Nothing changed.} one {# setting saved.} other {# settings saved.}}', ['count' => count($result['changed'])]);

        if ($result['applied'] !== []) {
            $message .= ' '.implode('. ', $result['applied']).'.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function reset(Request $request, SaveSystemSettings $save, ConfigRegistry $registry, ConfigOverlay $overlay): RedirectResponse
    {
        Gate::authorize(Capability::ManageSystem->value);

        $data = $request->validate(['key' => ['required', 'string', 'max:190']]);
        $parameter = $registry->find($data['key']);

        abort_if($parameter === null || $overlay->isSetByEnvironment($parameter) || ! $request->user()?->can($parameter->capability->value), 422, __('This setting cannot be reset here.'));

        $save->reset($request->user(), $parameter->key);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('":label" is back to its default.', ['label' => $parameter->translatedLabel($this->groupPlugins($registry->groups())[$parameter->group] ?? null)])]);

        return back();
    }

    // Write the recommended PHP limits into the managed block of public/.user.ini or public/.htaccess.
    public function applyIni(Request $request, ServerAdvisor $advisor, ManagedIniFile $iniFile, PhpIni $ini): RedirectResponse
    {
        Gate::authorize(Capability::ManageSystem->value);

        $data = $request->validate([
            'target' => ['required', Rule::enum(IniTarget::class)],
            'confirm' => ['accepted'],
        ]);
        $target = IniTarget::from($data['target']);
        $values = $advisor->recommended();

        if ($values === []) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('PHP limits already fit your settings.')]);

            return back();
        }

        try {
            $written = $iniFile->write($target, $values);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        ConfigChange::query()->create([
            'user_id' => $request->user()?->id,
            'action' => ConfigChange::INI_WRITE,
            'key' => null,
            'new_value' => $values,
            'details' => ['file' => 'public/'.basename($written['path']), 'backup' => $written['backup'] === null ? null : basename($written['backup'])],
        ]);

        $file = 'public/'.basename($written['path']);
        $message = $target === IniTarget::UserIni
            ? Translate::icu('PHP limits written to {file}. {seconds, plural, one {PHP reads it again within # second.} other {PHP reads it again within # seconds.}}', ['file' => $file, 'seconds' => max(0, (int) $ini->get('user_ini.cache_ttl'))])
            : __('PHP limits written to :file.', ['file' => $file]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @param  list<ConfigGroup>  $groups
     * @return array<string, string|null>
     */
    protected function groupPlugins(array $groups): array
    {
        $plugins = [];

        foreach ($groups as $group) {
            $plugins[$group->key] = $group->plugin;
        }

        return $plugins;
    }

    /**
     * @param  array<string, string|null>  $plugins
     */
    protected function settingLabel(ConfigRegistry $registry, array $plugins, string $key): ?string
    {
        $parameter = $registry->find($key);

        return $parameter?->translatedLabel($plugins[$parameter->group] ?? null);
    }
}
