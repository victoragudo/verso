<?php

namespace App\Verso\Extensions\Console;

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Benchmark\BenchmarkSeeder;
use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Runs inside the sandbox process: fills a temporary database with demo posts and renders pages with one theme.
class SandboxThemeCommand extends SandboxCommand
{
    // Pages rendered with the theme, by the names the demo seeder gives them.
    public const PAGES = ['home', 'entry', 'archive'];

    // Laravel adds "(View: <file>)" to errors thrown inside a view.
    protected const VIEW_PATTERN = '/\s*\(View: ([^)]+)\)\s*$/';

    protected $signature = 'verso:sandbox-theme {path : Theme folder inside the sandbox workspace} {--database= : The temporary SQLite file}';

    protected $description = 'Render one theme with demo content in the verification sandbox (used by verso:verify-theme)';

    /** @var array<string, mixed> */
    protected array $report = ['pages' => [], 'errors' => []];

    public function handle(ThemeManager $themes, Settings $settings, BenchmarkSeeder $seeder): int
    {
        $problem = $this->safetyProblem();

        if ($problem !== null) {
            return $this->finish([['stage' => 'setup', 'message' => $problem]]);
        }

        $slug = basename((string) $this->argument('path'));
        $paths = [];

        $error = $this->step('database', fn () => $this->callSilently('migrate', ['--force' => true]) === 0 ? null : 'The core migrations failed.')
            ?? $this->step('seed', function () use ($settings, $seeder, &$paths): null {
                $settings->forgetCache();
                $paths = array_intersect_key($seeder->seed(max(1, (int) config('verso.extensions.verification.themes.preview_posts'))), array_flip(self::PAGES));

                return null;
            })
            ?? $this->step('theme', function () use ($themes, $slug): null {
                $themes->refresh();
                $themes->activate($slug);

                return null;
            });

        if ($error !== null) {
            return $this->finish([$error]);
        }

        $errors = [];

        foreach (self::PAGES as $name) {
            $error = $this->render($name, (string) ($paths[$name] ?? '/'));

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $this->finish($errors);
    }

    /**
     * Send one request through the HTTP kernel and keep the result. Returns an error entry when the page broke.
     *
     * @return array{stage: string, message: string, file: string|null, line: int|null}|null
     */
    protected function render(string $name, string $path): ?array
    {
        $kernel = $this->laravel->make(Kernel::class);
        $request = Request::create(rtrim((string) config('app.url'), '/').$path, 'GET', server: ['HTTP_ACCEPT' => 'text/html']);
        $started = microtime(true);

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        } catch (Throwable $exception) {
            $this->addPage($name, $path, 500, microtime(true) - $started, '');

            return $this->viewError($name, $exception);
        } finally {
            $this->laravel->forgetScopedInstances();
        }

        $html = (string) $response->getContent();
        $this->addPage($name, $path, $response->getStatusCode(), microtime(true) - $started, $html);

        if ($response->exception instanceof Throwable) {
            return $this->viewError($name, $response->exception);
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return ['stage' => 'render', 'message' => "The {$name} page answered {$response->getStatusCode()} instead of 200.", 'file' => null, 'line' => null];
        }

        return null;
    }

    protected function addPage(string $name, string $path, int $status, float $seconds, string $html): void
    {
        $maxBytes = max(0, (int) config('verso.extensions.verification.themes.preview_max_kb')) * 1024;

        $this->report['pages'][] = [
            'name' => $name,
            'path' => $path,
            'status' => $status,
            'seconds' => round($seconds, 3),
            'bytes' => strlen($html),
            'html' => $maxBytes === 0 ? '' : mb_strcut($html, 0, $maxBytes),
        ];
    }

    /**
     * An error entry for a broken page. Errors inside a view point to the view file of the theme.
     *
     * @return array{stage: string, message: string, file: string|null, line: int|null}
     */
    protected function viewError(string $name, Throwable $exception): array
    {
        $error = $this->errorFrom('render', $exception);
        $base = rtrim((string) realpath((string) $this->argument('path')), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (preg_match(self::VIEW_PATTERN, $exception->getMessage(), $match) === 1) {
            $error['message'] = (string) preg_replace(self::VIEW_PATTERN, '', $error['message']);
            $view = (string) (realpath($match[1]) ?: $match[1]);

            if ($error['file'] === null && str_starts_with($view, $base)) {
                $error['file'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($view, strlen($base)));
            }
        }

        $error['message'] = "The {$name} page failed: ".$error['message'];

        return $error;
    }
}
