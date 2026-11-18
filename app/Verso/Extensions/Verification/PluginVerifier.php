<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Extensions\Verification\Checks\CompatibilityCheck;
use App\Verso\Extensions\Verification\Checks\PermissionCheck;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Sandbox\SandboxResult;
use App\Verso\Extensions\Verification\Sandbox\SandboxRunner;
use App\Verso\Plugins\Exceptions\InvalidManifest;
use App\Verso\Plugins\PluginManifest;
use JsonException;

// Runs every check on an unpacked plugin folder and builds the report.
class PluginVerifier
{
    public function __construct(
        protected CodeScanner $code,
        protected PermissionCheck $permissions,
        protected CompatibilityCheck $compatibility,
        protected SandboxRunner $sandbox,
    ) {}

    // Check the plugin in $path. $withSandbox false skips booting it in a separate process.
    public function verify(string $path, bool $withSandbox = true): VerificationReport
    {
        $path = rtrim($path, '/\\');
        $manifestFile = $path.DIRECTORY_SEPARATOR.config('verso.plugins.manifest');

        try {
            $manifest = PluginManifest::fromFile($manifestFile, false);
        } catch (InvalidManifest $exception) {
            return VerificationReport::make(basename($path), '0.0.0', [
                new Finding('manifest.invalid', Severity::High, message: 'The manifest is not valid: :errors', file: basename($manifestFile), replace: ['errors' => implode(' ', $exception->errors)]),
            ]);
        }

        $scan = new ScanResult;
        $files = $this->code->scan($path, $scan);
        $permissions = $this->permissions->check($manifest, $scan);
        $sandbox = $withSandbox ? $this->sandbox->run($path, $manifest->slug) : SandboxResult::skipped();

        $this->compatibility->check($manifest, $this->rawManifest($manifestFile), $path, $scan, $sandbox);
        $this->addSandboxFindings($sandbox, $scan);

        return VerificationReport::make($manifest->slug, $manifest->version, $scan->findings, [
            'name' => $manifest->name,
            'files' => $files,
            'permissions' => $permissions,
            'sandbox' => $sandbox->toArray(),
        ]);
    }

    // Turn a failed sandbox run into findings.
    protected function addSandboxFindings(SandboxResult $sandbox, ScanResult $scan): void
    {
        match ($sandbox->status) {
            SandboxResult::SKIPPED => $scan->add('sandbox.skipped', Severity::Info, message: 'The plugin was not booted in the sandbox.'),
            SandboxResult::TIMEOUT => $scan->add('sandbox.timeout', Severity::High, message: 'Booting the plugin in the sandbox took too long and was stopped.'),
            SandboxResult::CRASHED => $scan->add('sandbox.crashed', Severity::High, message: 'The sandbox process stopped without a result: :output', file: null, replace: ['output' => $sandbox->firstOutputLine()]),
            default => null,
        };

        foreach ($sandbox->errors as $error) {
            ['rule' => $rule, 'severity' => $severity, 'message' => $message] = match ($error['stage']) {
                'migrate' => ['rule' => 'sandbox.migration-failed', 'severity' => Severity::High, 'message' => 'The plugin migrations failed: :error'],
                'rollback' => ['rule' => 'sandbox.rollback-failed', 'severity' => Severity::Medium, 'message' => 'The plugin migrations could not be rolled back: :error'],
                'boot', 'manifest' => ['rule' => 'sandbox.boot-failed', 'severity' => Severity::High, 'message' => 'The plugin could not boot: :error'],
                default => ['rule' => 'sandbox.error', 'severity' => Severity::Medium, 'message' => 'The sandbox could not be prepared: :error'],
            };

            $scan->add($rule, $severity, $message, $error['file'], $error['line'], ['error' => $error['message']]);
        }
    }

    /**
     * @return array<mixed>
     */
    protected function rawManifest(string $file): array
    {
        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
