<?php

namespace App\Verso\Extensions\Verification\Sandbox;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

// Boots a plugin, or renders a theme, in a separate PHP process with its own temporary SQLite database.
class SandboxRunner
{
    // The sandbox command prints its JSON result after this marker.
    public const MARKER = '@@verso-sandbox@@';

    // Environment value the sandbox command checks before it touches any database.
    public const ENV_FLAG = 'VERSO_SANDBOX';

    public function __construct(
        protected Filesystem $files,
        protected string $workPath,
        protected bool $enabled,
        protected int $timeout,
        protected string $phpBinary,
        protected int $maxOutput,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // Copy the plugin into an empty workspace and boot it there with only this plugin.
    public function run(string $pluginPath, string $slug): SandboxResult
    {
        return $this->inWorkspace('verso:sandbox-plugin', function (string $workspace) use ($pluginPath, $slug): string {
            $target = $workspace.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.$slug;
            $this->files->copyDirectory($pluginPath, $target);

            return $target;
        });
    }

    /**
     * Copy the theme, its installed parents and the fallback theme into an empty workspace, then render pages with it.
     *
     * @param  array<string, string>  $support  slug => folder of other themes the theme needs, like its parents
     */
    public function runTheme(string $themePath, string $slug, array $support = []): SandboxResult
    {
        return $this->inWorkspace('verso:sandbox-theme', function (string $workspace) use ($themePath, $slug, $support): string {
            $themes = $workspace.DIRECTORY_SEPARATOR.'themes';

            foreach ($support as $supportSlug => $path) {
                if ($supportSlug !== $slug) {
                    $this->files->copyDirectory($path, $themes.DIRECTORY_SEPARATOR.$supportSlug);
                }
            }

            $this->files->copyDirectory($themePath, $themes.DIRECTORY_SEPARATOR.$slug);

            return $themes.DIRECTORY_SEPARATOR.$slug;
        }, fn (string $workspace): array => [
            'VERSO_THEMES_PATH' => $workspace.DIRECTORY_SEPARATOR.'themes',
            'VERSO_PAGE_CACHE' => 'false',
            'VERSO_BLOCK_CACHE' => 'false',
            'VERSO_INSTALL_REDIRECT' => 'false',
            // The child reads config from the environment, so the values of this process are passed on.
            'VERSO_VERIFY_THEME_PREVIEW_POSTS' => (string) (int) config('verso.extensions.verification.themes.preview_posts'),
            'VERSO_VERIFY_THEME_PREVIEW_MAX_KB' => (string) (int) config('verso.extensions.verification.themes.preview_max_kb'),
        ]);
    }

    /**
     * Make a workspace with empty plugin, theme and view folders and a fresh SQLite file, run the command, then delete it all.
     *
     * @param  callable(string): string  $prepare  copies the files in and returns the folder given to the command
     * @param  (callable(string): array<string, string>)|null  $environment  extra environment values for the workspace
     */
    protected function inWorkspace(string $command, callable $prepare, ?callable $environment = null): SandboxResult
    {
        if (! $this->enabled) {
            return SandboxResult::skipped();
        }

        $workspace = $this->workPath.DIRECTORY_SEPARATOR.Str::uuid();
        $database = $workspace.DIRECTORY_SEPARATOR.'database.sqlite';

        try {
            foreach (['plugins', 'themes', 'views'] as $folder) {
                $this->files->ensureDirectoryExists($workspace.DIRECTORY_SEPARATOR.$folder, 0755, true);
            }

            $this->files->put($database, '');
            $target = $prepare($workspace);

            $env = [...$this->environment($workspace, $database), ...($environment === null ? [] : $environment($workspace))];

            return $this->runProcess($command, $target, $database, $env);
        } finally {
            $this->files->deleteDirectory($workspace);
        }
    }

    /**
     * @param  array<string, string>  $env
     */
    protected function runProcess(string $command, string $target, string $database, array $env): SandboxResult
    {
        $process = new Process(
            command: [$this->php(), base_path('artisan'), $command, $target, '--database='.$database, '--no-interaction'],
            cwd: base_path(),
            env: $env,
            timeout: $this->timeout,
        );

        $started = microtime(true);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new SandboxResult(SandboxResult::TIMEOUT, microtime(true) - $started, output: $this->trim($process->getOutput().$process->getErrorOutput()));
        }

        $seconds = microtime(true) - $started;
        $output = $process->getOutput();
        $position = strrpos($output, self::MARKER);
        $log = $this->trim(($position === false ? $output : substr($output, 0, $position)).$process->getErrorOutput());

        if ($position === false) {
            return new SandboxResult(SandboxResult::CRASHED, $seconds, output: $log);
        }

        try {
            $data = json_decode(trim(substr($output, $position + strlen(self::MARKER))), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new SandboxResult(SandboxResult::CRASHED, $seconds, output: $log);
        }

        return SandboxResult::fromArray(is_array($data) ? $data : [], $seconds, $log);
    }

    /**
     * Environment for the child: a fresh SQLite file, no caches, no other plugins.
     *
     * @return array<string, string>
     */
    protected function environment(string $workspace, string $database): array
    {
        $cache = $workspace.DIRECTORY_SEPARATOR.'cache';

        return [
            self::ENV_FLAG => '1',
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'LOG_CHANNEL' => 'stderr',
            'APP_CONFIG_CACHE' => $cache.DIRECTORY_SEPARATOR.'config.php',
            'APP_ROUTES_CACHE' => $cache.DIRECTORY_SEPARATOR.'routes.php',
            'APP_EVENTS_CACHE' => $cache.DIRECTORY_SEPARATOR.'events.php',
            'VIEW_COMPILED_PATH' => $workspace.DIRECTORY_SEPARATOR.'views',
            'VERSO_PLUGINS_PATHS' => $workspace.DIRECTORY_SEPARATOR.'plugins',

            'VERSO_PLUGINS_COMPOSER_INSTALLED' => '',
            'VERSO_PLUGINS_MIGRATE_ON_ENABLE' => 'false',
        ];
    }

    protected function php(): string
    {
        if ($this->phpBinary !== '') {
            return $this->phpBinary;
        }

        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }

    protected function trim(string $output): string
    {
        $output = trim($output);

        return mb_strlen($output) > $this->maxOutput ? mb_substr($output, 0, $this->maxOutput).'…' : $output;
    }
}
