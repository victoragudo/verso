<?php

namespace App\Verso\Install;

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Verso\Install\Console\InstallCommand;
use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Database\DatabaseCreator;
use App\Verso\Install\Database\PdoConnector;
use App\Verso\Install\Env\EnvWriter;
use App\Verso\Install\Http\Controllers\WelcomeController;
use App\Verso\Install\Http\Middleware\EnsureNotInstalled;
use App\Verso\Install\Http\Middleware\RedirectToInstaller;
use App\Verso\Install\Pipeline\InstallPipeline;
use App\Verso\Install\Pipeline\InstallProgress;
use App\Verso\Install\Pipeline\RuntimeConfig;
use App\Verso\Install\Pipeline\Steps\WriteEnvironment;
use App\Verso\Install\Services\ServiceTester;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Boots the install wizard at /install, the redirect to it and the verso:install command.
class InstallServiceProvider extends ServiceProvider
{
    // Middleware of the wizard routes: the web group without anything that reads the database.
    public const MIDDLEWARE_GROUP = 'verso.installer';

    public function register(): void
    {
        $this->app->singleton(InstallProgress::class, fn (Application $app): InstallProgress => new InstallProgress(
            files: $app->make(Filesystem::class),
            path: (string) config('verso.install.state_file'),
        ));

        $this->app->singleton(InstallState::class, fn (Application $app): InstallState => new InstallState(
            files: $app->make(Filesystem::class),
            lockFile: (string) config('verso.install.lock_file'),
            progress: $app->make(InstallProgress::class),
        ));

        $this->app->singleton(InstallerMode::class);

        $this->app->when(InstallPipeline::class)->needs('$restartWaitSeconds')->giveConfig('verso.install.restart_wait_seconds', 5);

        $this->app->bind(Requirements::class, fn (Application $app): Requirements => new Requirements(
            phpVersion: (string) config('verso.install.php_version'),
            extensions: array_values(array_map(strval(...), (array) config('verso.install.extensions'))),
            writable: array_values(array_map(strval(...), (array) config('verso.install.writable'))),
            basePath: $app->basePath(),
            imageExtensions: array_values(array_map(strval(...), (array) config('verso.install.image_extensions'))),
            memoryLimit: (string) config('verso.install.memory_limit'),
            envPath: $this->envPath($app),
        ));

        $this->app->bind(EnvWriter::class, fn (Application $app): EnvWriter => new EnvWriter(
            $app->make(Filesystem::class),
            (int) config('verso.install.env_permissions'),
        ));

        $this->app->bind(DatabaseConnectionTester::class, fn (Application $app): DatabaseConnectionTester => new DatabaseConnectionTester(
            connector: $app->make(PdoConnector::class),
            minVersions: array_map(strval(...), (array) config('verso.install.database.min_versions')),
            drivers: (array) config('verso.install.database.drivers'),
            versoTables: array_values(array_map(strval(...), (array) config('verso.install.database.verso_tables'))),
            basePath: $app->basePath(),
            timeout: (int) config('verso.install.connect_timeout'),
        ));

        $this->app->bind(DatabaseCreator::class, fn (Application $app): DatabaseCreator => new DatabaseCreator(
            $app->make(PdoConnector::class),
            $app->make(DatabaseConnectionTester::class),
            $app->make(Filesystem::class),
            $app->basePath(),
        ));

        $this->app->bind(ServiceTester::class, fn (Application $app): ServiceTester => new ServiceTester(
            $app->make('config'),
            $app->make('mail.manager'),
            $app->make('redis'),
            $app->make('filesystem'),
            (int) config('verso.install.connect_timeout'),
        ));

        $this->app->bind(RuntimeConfig::class, fn (Application $app): RuntimeConfig => new RuntimeConfig(
            $app->make('config'),
            $app->make('db'),
            $app->basePath(),
        ));

        $this->app->bind(WriteEnvironment::class, fn (Application $app): WriteEnvironment => new WriteEnvironment(
            $app->make(EnvWriter::class),
            $app->make(ConsoleKernel::class),
            $app->make('config'),
            $this->envPath($app),
        ));

        $this->switchToSafeDriversEarly();
    }

    public function boot(): void
    {
        RateLimiter::for('verso-install-tests', fn (Request $request): Limit => Limit::perMinute((int) config('verso.install.test_attempts_per_minute'))->by($request->ip()));

        $router = $this->app->make(Router::class);
        $router->middlewareGroup(self::MIDDLEWARE_GROUP, [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
        ]);

        Route::middleware([self::MIDDLEWARE_GROUP, EnsureNotInstalled::class])
            ->prefix('install')
            ->name('install.')
            ->group(base_path('routes/verso/install.php'));

        Route::middleware(['web', 'signed:relative'])
            ->get('verso/welcome/{user}', WelcomeController::class)
            ->whereNumber('user')
            ->name('verso.welcome');

        $this->app->make(HttpKernel::class)->prependMiddleware(RedirectToInstaller::class);

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }

    // The .env file the installer writes.
    protected function envPath(Application $app): string
    {
        $configured = (string) config('verso.install.env_file');

        return $configured !== '' ? $configured : $app->environmentFilePath();
    }

    // Before any provider reads settings, a site that is not installed must not use database sessions or cache.
    protected function switchToSafeDriversEarly(): void
    {
        if (! (bool) config('verso.install.redirect') || $this->app->runningUnitTests() || is_file((string) config('verso.install.lock_file'))) {
            return;
        }

        $installing = $this->app->runningInConsole()
            ? ($_SERVER['argv'][1] ?? null) === 'verso:install'
            : true;

        if ($installing) {
            $this->app->make(InstallerMode::class)->activate();
        }
    }
}
