<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Exceptions\StagedPackageNotFound;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Plugins\PluginManager;
use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    $this->installer = app(ExtensionInstaller::class);
    $this->stage = fn (PackageZip $zip, ExtensionKind $kind = ExtensionKind::Plugin) => $this->installer->stage($zip->build($this->sandbox->zipPath(uniqid())), $kind);
    $this->installPlugin = fn (string $version, bool $activate = false, array $manifest = [], ?PackageZip $zip = null) => $this->installer->install(($this->stage)($zip ?? PackageZip::plugin('tour-dates', $version, $manifest))->token, $activate);
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('stages a checked package and waits for consent', function () {
    $staged = ($this->stage)(PackageZip::plugin('tour-dates', '1.0.0'));

    expect($staged->package->slug)->toBe('tour-dates')
        ->and($staged->source)->toBe('upload')
        ->and($staged->isUpdate())->toBeFalse()
        ->and($staged->sha256)->toHaveLength(64)
        ->and(File::exists($staged->zipPath))->toBeTrue()
        ->and(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse()
        ->and($this->installer->staged($staged->token)->package->version)->toBe('1.0.0');
});

it('does not stage an unsafe package', function () {
    expect(fn () => ($this->stage)(PackageZip::plugin()->add('../escape.php', '<?php')))->toThrow(InvalidPackage::class);

    expect(File::exists($this->sandbox->root.'/storage/staging'))->toBeFalse();
});

it('refuses unknown or malformed staging tokens', function (string $token) {
    expect(fn () => $this->installer->staged($token))->toThrow(StagedPackageNotFound::class);
})->with([
    'path traversal' => '../../../.env',
    'unknown uuid' => '0f8fad5b-d9cb-469f-a165-70867728950e',
]);

it('forgets staged packages after the time limit', function () {
    $staged = ($this->stage)(PackageZip::plugin());

    $this->travel(2)->hours();

    expect(fn () => $this->installer->staged($staged->token))->toThrow(StagedPackageNotFound::class);
    expect(File::exists(dirname($staged->zipPath)))->toBeFalse();
});

it('checks the staged zip again before installing it', function () {
    $staged = ($this->stage)(PackageZip::plugin());
    PackageZip::plugin()->add('tour-dates/public/shell.php', '<?php system($_GET["c"]);')->build($staged->zipPath);

    expect(fn () => $this->installer->install($staged->token))->toThrow(InvalidPackage::class, 'outside the allowed folders');
    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse();
});

it('installs a plugin without enabling it', function () {
    $result = ($this->installPlugin)('1.0.0');

    expect($result->slug)->toBe('tour-dates')
        ->and($result->activated)->toBeFalse()
        ->and($result->message())->toBe('Tour Dates 1.0.0 installed.')
        ->and(File::exists($this->sandbox->pluginsPath().'/tour-dates/verso.json'))->toBeTrue()
        ->and(app(PluginManager::class)->isEnabled('tour-dates'))->toBeFalse()
        ->and(File::isDirectory($this->sandbox->root.'/storage/tmp') && File::directories($this->sandbox->root.'/storage/tmp') !== [])->toBeFalse();
});

it('installs and enables a plugin and runs its migrations', function () {
    $zip = PackageZip::plugin('tour-dates', '1.0.0')->add('tour-dates/database/migrations/2026_12_01_000000_create_tour_dates_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('tour_dates', function (Blueprint $table) {
                    $table->id();
                    $table->string('city');
                });
            }
        };
        PHP);

    $result = ($this->installPlugin)('1.0.0', activate: true, zip: $zip);

    expect($result->activated)->toBeTrue()
        ->and($result->message())->toBe('Tour Dates 1.0.0 installed and enabled.')
        ->and(app(PluginManager::class)->isEnabled('tour-dates'))->toBeTrue()
        ->and(Schema::hasTable('tour_dates'))->toBeTrue();
});

it('updates a plugin, keeps it enabled and backs up the old version', function () {
    ($this->installPlugin)('1.0.0', activate: true);

    $result = ($this->installPlugin)('1.1.0');

    $backups = File::directories($this->installer->backupFolder(ExtensionKind::Plugin, 'tour-dates'));

    expect($result->isUpdate())->toBeTrue()
        ->and($result->previousVersion)->toBe('1.0.0')
        ->and($result->activated)->toBeTrue()
        ->and($result->message())->toBe('Tour Dates updated to 1.1.0 and enabled.')
        ->and(app(PluginManager::class)->get('tour-dates')->manifest->version)->toBe('1.1.0')
        ->and($backups)->toHaveCount(1)
        ->and($backups[0])->toEndWith('-1.0.0')
        ->and(json_decode(File::get($backups[0].'/verso.json'), true)['version'])->toBe('1.0.0');
});

it('rolls an update back when the new version cannot be enabled', function () {
    ($this->installPlugin)('1.0.0', activate: true);

    $broken = PackageZip::plugin('tour-dates', '2.0.0')->add('tour-dates/database/migrations/2026_12_02_000000_add_venue.php', "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration\n{\n    public function up(): void\n    {\n        throw new RuntimeException('The venues table is missing.');\n    }\n};\n");

    expect(fn () => ($this->installPlugin)('2.0.0', zip: $broken))
        ->toThrow(ExtensionInstallFailed::class, 'rolled back');

    expect(app(PluginManager::class)->get('tour-dates')->manifest->version)->toBe('1.0.0')
        ->and(app(PluginManager::class)->isEnabled('tour-dates'))->toBeTrue()
        ->and(File::exists($this->sandbox->pluginsPath().'/tour-dates/database'))->toBeFalse();
});

it('removes a fresh install that cannot be enabled', function () {
    $zip = PackageZip::plugin('tour-dates', '1.0.0', ['provider' => 'Verso\\Plugins\\TourDates\\MissingProvider']);

    expect(fn () => ($this->installPlugin)('1.0.0', activate: true, zip: $zip))->toThrow(ExtensionInstallFailed::class, 'was not found');

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse()
        ->and(app(Settings::class)->get('plugins.enabled'))->toBe([]);
});

it('refuses to install an older version', function () {
    ($this->installPlugin)('1.2.0');

    expect(fn () => ($this->installPlugin)('1.1.9'))->toThrow(ExtensionInstallFailed::class, 'older than the installed version 1.2.0');
    expect(app(PluginManager::class)->get('tour-dates')->manifest->version)->toBe('1.2.0');
});

it('keeps only the newest backups', function () {
    config(['verso.extensions.backups_kept' => 1]);
    $this->installer = app(ExtensionInstaller::class);

    foreach (['1.0.0', '1.1.0', '1.2.0', '1.3.0'] as $version) {
        ($this->installPlugin)($version);
    }

    $backups = File::directories($this->installer->backupFolder(ExtensionKind::Plugin, 'tour-dates'));

    expect($backups)->toHaveCount(1)->and($backups[0])->toEndWith('-1.2.0');
});

it('removes a plugin after disabling it', function () {
    ($this->installPlugin)('1.0.0', activate: true);

    $this->installer->remove(ExtensionKind::Plugin, 'tour-dates');

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse()
        ->and(app(PluginManager::class)->isEnabled('tour-dates'))->toBeFalse();
});

it('only removes plugins from the install folder', function () {
    config(['verso.plugins.paths' => [$this->sandbox->pluginsPath(), base_path('tests/Fixtures/plugins')]]);
    app()->forgetInstance(PluginManager::class);
    app()->forgetInstance(PluginDiscovery::class);
    $installer = app(ExtensionInstaller::class);

    expect($installer->removalProblem(ExtensionKind::Plugin, 'event-calendar'))->toContain('is not installed in')
        ->and(fn () => $installer->remove(ExtensionKind::Plugin, 'event-calendar'))->toThrow(ExtensionInstallFailed::class);

    expect(File::exists(base_path('tests/Fixtures/plugins/event-calendar/verso.json')))->toBeTrue();
});

it('installs, activates and updates a theme', function () {
    $result = $this->installer->install(($this->stage)(PackageZip::theme('lighthouse', '1.0.0'), ExtensionKind::Theme)->token, true);

    expect($result->message())->toBe('Lighthouse 1.0.0 installed and active.')
        ->and(app(ThemeManager::class)->active()->slug)->toBe('lighthouse');

    $update = $this->installer->install(($this->stage)(PackageZip::theme('lighthouse', '1.1.0'), ExtensionKind::Theme)->token);

    expect($update->activated)->toBeTrue()
        ->and(app(ThemeManager::class)->find('lighthouse')->version)->toBe('1.1.0');
});

it('rolls a theme update back when the new theme.json is broken', function () {
    $this->installer->install(($this->stage)(PackageZip::theme('lighthouse', '1.0.0'), ExtensionKind::Theme)->token);
    $staged = ($this->stage)(PackageZip::theme('lighthouse', '1.1.0'), ExtensionKind::Theme);

    $this->mock(ActivateTheme::class)
        ->shouldReceive('handle')->andThrow(new RuntimeException('Views could not compile.'));

    expect(fn () => app(ExtensionInstaller::class)->install($staged->token, true))
        ->toThrow(ExtensionInstallFailed::class, 'Views could not compile.');

    expect(app(ThemeManager::class)->find('lighthouse')->version)->toBe('1.0.0');
});

it('protects the active theme, and the fallback theme while the active one still needs its views', function () {
    $this->installer->install(($this->stage)(PackageZip::theme('lighthouse'), ExtensionKind::Theme)->token, true);

    expect(fn () => $this->installer->remove(ExtensionKind::Theme, 'lighthouse'))->toThrow(ExtensionInstallFailed::class, 'in use');
    expect(fn () => $this->installer->remove(ExtensionKind::Theme, 'default'))
        ->toThrow(ExtensionInstallFailed::class, 'still uses these views from this theme: layouts/app, entry, archive, taxonomy, search, 404');

    app(ThemeManager::class)->activate('default');
    $this->installer->remove(ExtensionKind::Theme, 'lighthouse');

    expect(File::exists($this->sandbox->themesPath().'/lighthouse'))->toBeFalse();
});

it('protects the parent of the active child theme from removal', function () {
    $this->installer->install(($this->stage)(PackageZip::theme('lighthouse'), ExtensionKind::Theme)->token);
    $this->installer->install(($this->stage)(PackageZip::theme('lighthouse-night', '1.0.0', ['parent' => 'lighthouse']), ExtensionKind::Theme)->token, true);

    expect($this->installer->removalProblem(ExtensionKind::Theme, 'lighthouse'))->toBe('The active theme is a child of this theme. Activate another theme first.');

    app(ThemeManager::class)->activate('default');

    expect($this->installer->removalProblem(ExtensionKind::Theme, 'lighthouse'))->toBeNull();
});

it('rolls back the install of a child theme activated without its parent', function () {
    $staged = ($this->stage)(PackageZip::theme('lighthouse-night', '1.0.0', ['parent' => 'lighthouse']), ExtensionKind::Theme);

    expect(fn () => $this->installer->install($staged->token, true))->toThrow(ExtensionInstallFailed::class, 'parent theme [lighthouse]');

    expect(app(Settings::class)->get('theme.active'))->not->toBe('lighthouse-night');
});

it('discards staged packages', function () {
    $staged = ($this->stage)(PackageZip::plugin());

    $this->installer->discard($staged->token);
    $this->installer->discard('../../not-a-token');

    expect(fn () => $this->installer->staged($staged->token))->toThrow(StagedPackageNotFound::class);
});

it('removes the fallback theme when the active theme has every required view', function () {
    $complete = PackageZip::theme('harbour');

    foreach (ThemeManager::REQUIRED_VIEWS as $view) {
        $complete->add('harbour/views/'.$view.'.blade.php', "<p>Harbour {$view}</p>\n");
    }

    $this->installer->install(($this->stage)($complete, ExtensionKind::Theme)->token, true);

    expect($this->installer->removalProblem(ExtensionKind::Theme, 'default'))->toBeNull();

    $this->installer->remove(ExtensionKind::Theme, 'default');

    $themes = app(ThemeManager::class);
    expect(File::exists($this->sandbox->themesPath().'/default'))->toBeFalse()
        ->and($themes->fallback())->toBeNull()
        ->and($themes->active()->slug)->toBe('harbour')
        ->and($themes->viewPaths()[0])->toEndWith('/harbour/views');
});
