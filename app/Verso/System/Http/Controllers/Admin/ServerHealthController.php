<?php

namespace App\Verso\System\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use App\Verso\I18n\Translate;
use App\Verso\System\Health\HealthCheck;
use App\Verso\System\Health\PermissionFixer;
use App\Verso\System\Health\PermissionInspector;
use App\Verso\System\Health\ProcessIdentity;
use App\Verso\System\Models\ConfigChange;
use App\Verso\System\Server\PhpIni;
use App\Verso\System\Server\ServerAdvisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// The Server health page: PHP, extensions, ini values, folders, modes and risky states, with safe fixes.
class ServerHealthController extends Controller
{
    public function index(PermissionInspector $inspector, PermissionFixer $fixer, PhpIni $ini, ServerAdvisor $advisor, ProcessIdentity $identity): Response
    {
        Gate::authorize(Capability::ManageSystem->value);

        $checks = $inspector->checks();
        $loaded = array_map(strtolower(...), $ini->extensions());
        $required = array_values(array_map(strval(...), (array) config('verso.install.extensions')));
        $images = array_values(array_map(strval(...), (array) config('verso.install.image_extensions')));

        return Inertia::render('admin/system/health', [
            'php' => [
                'version' => $ini->version(),
                'minimum' => (string) config('verso.install.php_version'),
                'sapi' => $ini->sapi(),
                'webServer' => $advisor->webServer()->label(),
                'iniFile' => $ini->loadedFile(),
                'user' => $identity->user,
                'group' => $identity->group,
            ],
            'extensions' => [
                ...array_map(fn (string $name): array => ['name' => $name, 'loaded' => in_array(strtolower($name), $loaded, true), 'required' => true], $required),
                ...array_map(fn (string $name): array => ['name' => $name, 'loaded' => in_array(strtolower($name), $loaded, true), 'required' => false], $images),
            ],
            'loadedExtensions' => $ini->extensions(),
            'ini' => $advisor->iniValues(),
            'checks' => array_map(fn (HealthCheck $check): array => $check->toArray(), $checks),
            'plan' => $fixer->run($inspector->plan(), dryRun: true),
            'modes' => [
                'folders' => __(':first or :second', ['first' => '0755', 'second' => '0775']),
                'files' => __(':first or :second', ['first' => '0644', 'second' => '0664']),
                'env' => '0640',
            ],
            'settingsUrl' => route('admin.system.settings.index'),
        ]);
    }

    // Run every safe fix. The page showed the same list as a dry run before.
    public function fix(Request $request, PermissionInspector $inspector, PermissionFixer $fixer): RedirectResponse
    {
        Gate::authorize(Capability::ManageSystem->value);

        $request->validate(['confirm' => ['accepted']]);
        $results = $fixer->run($inspector->plan(), dryRun: false);
        $done = count(array_filter($results, fn (array $result): bool => $result['done']));
        $failed = count($results) - $done;

        if ($results !== []) {
            ConfigChange::query()->create([
                'user_id' => $request->user()?->id,
                'action' => ConfigChange::PERMISSIONS_FIX,
                'details' => ['results' => $results],
            ]);
        }

        Inertia::flash('toast', [
            'type' => $failed === 0 ? 'success' : 'error',
            'message' => match (true) {
                $results === [] => __('There was nothing to fix.'),
                $failed > 0 => Translate::icu('{done} fixed, {failed} could not be fixed. Use the shell commands.', ['done' => $done, 'failed' => $failed]),
                default => Translate::icu('{done} fixed.', ['done' => $done]),
            },
        ]);

        return back();
    }
}
