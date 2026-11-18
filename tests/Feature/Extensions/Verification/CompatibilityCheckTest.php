<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Verification\Checks\CompatibilityCheck;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\Sandbox\SandboxResult;
use App\Verso\Extensions\Verification\Scanners\PhpScanner;
use App\Verso\Extensions\Verification\ScanResult;
use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\PluginManifest;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;
use Tests\Support\ExtensionSandbox;
use Tests\Support\VerificationFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.plugins.composer_installed' => null]);
    app()->forgetInstance(PluginDiscovery::class);
    app()->forgetInstance(PluginManager::class);

    // Scan a plugin folder and run the compatibility check on it.
    $this->check = function (string $path, ?SandboxResult $sandbox = null): ScanResult {
        $scan = new ScanResult;
        $scanner = app(PhpScanner::class);

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() === 'php') {
                $scanner->scan(str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()), $file->getContents(), $scan);
            }
        }

        $data = json_decode((string) file_get_contents($path.'/verso.json'), true);
        app(CompatibilityCheck::class)->check(PluginManifest::fromArray($data, $path, false), $data, $path, $scan, $sandbox ?? SandboxResult::skipped());

        return $scan;
    };
});

afterEach(function () {
    VerificationFixtures::cleanup();
    $this->sandbox->destroy();
});

// Rule and message of each finding.
function compatFindings(ScanResult $scan): array
{
    return array_map(fn (Finding $finding): array => [$finding->rule, $finding->message], $scan->findings);
}

it('finds nothing for a plugin that fits the site', function () {
    expect(($this->check)(VerificationFixtures::path('harbor-tides'))->findings)->toBe([]);
});

it('reports Verso and PHP versions the site does not meet', function () {
    $findings = compatFindings(($this->check)(VerificationFixtures::make('tour-dates', manifest: ['requires' => '^2.0', 'php' => '>=99.1'])));

    expect($findings)->toContain(
        ['compat.verso-version', 'The plugin needs Verso ^2.0, but this site runs Verso '.config('verso.version').'.'],
        ['compat.php-version', 'The plugin needs PHP >=99.1, but this site runs PHP '.PHP_VERSION.'.'],
    );
});

it('reads Composer style PHP constraints', function (string $constraint, ?string $rule) {
    $path = VerificationFixtures::make('tour-dates', ['composer.json' => json_encode(['require' => ['php' => $constraint]])]);

    expect(array_column(compatFindings(($this->check)($path)), 0))->toBe($rule === null ? [] : [$rule]);
})->with([
    'caret or caret' => ['^8.4|^9.0', null],
    'range with comma' => ['>=8.4,<10.0', null],
    'too new' => ['^12.0', 'compat.php-version'],
    'unreadable' => ['8.x-dev', 'compat.php-version'],
]);

it('reports missing Composer packages, old packages and missing extensions', function () {
    $path = VerificationFixtures::make('tour-dates', ['composer.json' => json_encode(['require' => [
        'php' => '>=8.4',
        'ext-json' => '*',
        'ext-tour_maps' => '*',
        'songkick/api-client' => '^3.0',
        'laravel/framework' => '^1.0',
        'pestphp/pest' => '*',
    ]])]);

    expect(compatFindings(($this->check)($path)))->toBe([
        ['compat.php-extension', 'The plugin needs the PHP extension "tour_maps", which is not loaded.'],
        ['compat.composer-require', 'The plugin needs the Composer package songkick/api-client (^3.0), which this site does not have.'],
        ['compat.composer-require', 'The plugin needs laravel/framework ^1.0, but this site has '.ltrim((string) InstalledVersions::getPrettyVersion('laravel/framework'), 'v').'.'],
    ]);
});

it('reports tables that already exist or belong to another plugin', function () {
    VerificationFixtures::install('harbor-tides', $this->sandbox->pluginsPath());
    $migration = fn (string $table) => "<?php\n\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$table}', function (\$table) {});\n    }\n};\n";

    $path = VerificationFixtures::make('tide-charts', [
        'database/migrations/2026_12_01_000000_create_users.php' => $migration('users'),
        'database/migrations/2026_12_01_000001_create_tides.php' => $migration('harbor_tide_times'),
        'database/migrations/2026_12_01_000002_create_charts.php' => $migration('tide_charts'),
    ]);

    expect(compatFindings(($this->check)($path)))->toBe([
        ['compat.table-clash', 'The table "users" is already used by this site.'],
        ['compat.table-clash', 'The table "harbor_tide_times" is already used by Harbor Tides.'],
    ]);
});

it('does not report the tables of the installed version of the same plugin', function () {
    VerificationFixtures::install('harbor-tides', $this->sandbox->pluginsPath());
    $copy = VerificationFixtures::make('harbor-tides');
    File::copyDirectory(VerificationFixtures::path('harbor-tides'), $copy);

    expect(($this->check)($copy)->findings)->toBe([]);
});

it('reports config keys used by the site or another plugin', function () {
    $other = VerificationFixtures::make('ticket-shop', ['config/tickets.php' => "<?php\n\nreturn [];\n"]);
    File::copyDirectory($other, $this->sandbox->pluginsPath().'/ticket-shop');

    $path = VerificationFixtures::make('tour-dates', [
        'config/verso.php' => "<?php\n\nreturn [];\n",
        'config/tickets.php' => "<?php\n\nreturn [];\n",
        'config/tour-dates.php' => "<?php\n\nreturn [];\n",
    ]);

    expect(compatFindings(($this->check)($path)))->toBe([
        ['compat.config-clash', 'The config key "tickets" is already used by Ticket Shop.'],
        ['compat.config-clash', 'The config key "verso" is already used by this site.'],
    ]);
});

it('reports classes and namespaces that already exist', function () {
    File::copyDirectory(base_path('plugins/reading-time'), $this->sandbox->pluginsPath().'/reading-time');

    $scan = ($this->check)(VerificationFixtures::path('calendar-clash'));
    $classes = array_values(array_filter(compatFindings($scan), fn (array $finding) => $finding[0] === 'compat.class-clash'));

    expect($classes)->toBe([
        ['compat.class-clash', 'The namespace Verso\\Plugins\\ReadingTime\\ is already used by Reading Time.'],
        ['compat.class-clash', 'The class App\\Models\\User already exists on this site.'],
    ]);
});

it('reports routes from the sandbox that reuse names or addresses', function () {
    $sandbox = new SandboxResult(SandboxResult::PASSED, routes: [
        ['uri' => 'calendar/sign-in', 'name' => 'login', 'methods' => ['GET']],
        ['uri' => 'feed', 'name' => 'calendar.feed', 'methods' => ['GET']],
        ['uri' => 'feed', 'name' => 'calendar.feed.store', 'methods' => ['POST']],
        ['uri' => 'calendar/upcoming', 'name' => 'calendar.upcoming', 'methods' => ['GET']],
    ]);

    expect(compatFindings(($this->check)(VerificationFixtures::make('tour-dates'), $sandbox)))->toBe([
        ['compat.route-clash', 'The route name "login" is already used on this site.'],
        ['compat.route-clash', 'The address GET /feed is already used on this site.'],
    ]);
});

it('reports content types and taxonomies the plugin would replace', function () {
    $sandbox = new SandboxResult(SandboxResult::PASSED, registered: [
        'content_types' => ['post', 'concert'],
        'taxonomies' => ['category'],
        'blocks' => ['setlist'],
    ]);

    expect(compatFindings(($this->check)(VerificationFixtures::make('tour-dates'), $sandbox)))->toBe([
        ['compat.slug-clash', 'The content type "post" is already registered on this site.'],
        ['compat.slug-clash', 'The taxonomy "category" is already registered on this site.'],
    ]);
});

it('reports a slug already used by a Composer package plugin', function () {
    config(['verso.plugins.composer_installed' => base_path('tests/Fixtures/composer/vendor/composer/installed.json')]);
    app()->forgetInstance(PluginDiscovery::class);
    app()->forgetInstance(PluginManager::class);
    app(Settings::class)->forgetCache();

    expect(compatFindings(($this->check)(VerificationFixtures::make('podcast-player'))))
        ->toContain(['compat.slug-clash', 'The slug "podcast-player" is already used by a plugin installed with Composer.']);
});
