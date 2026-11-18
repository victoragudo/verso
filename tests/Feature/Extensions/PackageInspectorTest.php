<?php

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Packages\PackageInspector;
use Illuminate\Support\Facades\File;
use Tests\Support\PackageZip;

beforeEach(function () {
    $this->workDir = storage_path('framework/testing/inspector-'.uniqid());
    $this->inspector = app(PackageInspector::class);
    $this->zip = fn (PackageZip $package): string => $package->build($this->workDir.'/'.uniqid().'.zip');
    $this->refusal = function (PackageZip $package, ExtensionKind $kind = ExtensionKind::Plugin): string {
        try {
            $this->inspector->inspect(($this->zip)($package), $kind);
        } catch (InvalidPackage $exception) {
            return implode(' ', $exception->errors);
        }

        return 'accepted';
    };
});

afterEach(function () {
    File::deleteDirectory($this->workDir);
});

it('reads a valid plugin package', function () {
    $package = $this->inspector->inspect(($this->zip)(PackageZip::plugin('tour-dates', '1.4.2')), ExtensionKind::Plugin);

    expect($package->kind)->toBe(ExtensionKind::Plugin)
        ->and($package->slug)->toBe('tour-dates')
        ->and($package->name)->toBe('Tour Dates')
        ->and($package->version)->toBe('1.4.2')
        ->and($package->author)->toBe('Lucia Moreno')
        ->and($package->requires)->toBe('^0.1')
        ->and($package->permissions)->toBe(['manage_terms', 'upload_files'])
        ->and(array_keys($package->files))->toBe(['verso.json', 'src/TourDatesServiceProvider.php', 'README.md'])
        ->and($package->unpackedBytes)->toBeGreaterThan(0);
});

it('reads a plugin package without a top folder', function () {
    $package = $this->inspector->inspect(($this->zip)(PackageZip::plugin('tour-dates', root: '')), ExtensionKind::Plugin);

    expect($package->files)->toHaveKey('verso.json')
        ->and($package->files)->toHaveKey('src/TourDatesServiceProvider.php');
});

it('accepts any top folder name, like a GitHub download', function () {
    $package = $this->inspector->inspect(($this->zip)(PackageZip::plugin('tour-dates', root: 'tour-dates-main')), ExtensionKind::Plugin);

    expect($package->slug)->toBe('tour-dates')
        ->and($package->files)->toHaveKey('verso.json');
});

it('reads a valid theme package', function () {
    $package = $this->inspector->inspect(($this->zip)(PackageZip::theme('lighthouse', '2.0.0')), ExtensionKind::Theme);

    expect($package->kind)->toBe(ExtensionKind::Theme)
        ->and($package->slug)->toBe('lighthouse')
        ->and($package->name)->toBe('Lighthouse')
        ->and($package->author)->toBe('Ines Duarte')
        ->and($package->permissions)->toBe([])
        ->and(array_keys($package->files))->toBe(['theme.json', 'views/home.blade.php', 'assets/theme.css']);
});

it('takes the theme slug from the top folder when theme.json has none', function () {
    $zip = PackageZip::theme('lighthouse');
    $zip->add('lighthouse/theme.json', json_encode(['name' => 'Lighthouse', 'version' => '1.0.0']));

    expect($this->inspector->inspect(($this->zip)($zip), ExtensionKind::Theme)->slug)->toBe('lighthouse');
});

it('skips macOS junk files', function () {
    $zip = PackageZip::plugin()->add('__MACOSX/tour-dates/._verso.json', 'junk')->add('tour-dates/.DS_Store', 'junk');

    $package = $this->inspector->inspect(($this->zip)($zip), ExtensionKind::Plugin);

    expect(array_keys($package->files))->toBe(['verso.json', 'src/TourDatesServiceProvider.php', 'README.md']);
});

it('refuses entries that point outside the package folder', function (string $name) {
    expect(($this->refusal)(PackageZip::plugin()->add($name, '<?php echo "owned";')))
        ->toContain('points outside the package folder');
})->with([
    'parent folder' => '../evil.php',
    'deep parent folder' => 'tour-dates/src/../../../../bootstrap/app.php',
    'parent at the end' => 'tour-dates/..',
]);

it('refuses absolute paths', function (string $name) {
    expect(($this->refusal)(PackageZip::plugin()->add($name, 'payload')))->toContain('is an absolute path');
})->with([
    'unix root' => '/etc/cron.d/verso',
    'windows drive' => 'C:/Windows/system32/verso.dll',
]);

it('refuses backslashes in entry names', function () {
    expect(($this->refusal)(PackageZip::plugin()->add('tour-dates\\..\\..\\evil.php', 'payload')))
        ->toContain('uses backslashes');
});

it('refuses empty and dot folders in paths', function (string $name) {
    expect(($this->refusal)(PackageZip::plugin()->add($name, 'payload')))->toContain('empty or "." folder');
})->with([
    'double slash' => 'tour-dates//src/Hidden.php',
    'dot folder' => 'tour-dates/./src/Hidden.php',
]);

it('refuses control characters in entry names', function () {
    expect(($this->refusal)(PackageZip::plugin()->add("tour-dates/src/Event\x07\n.php.jpg", 'payload')))
        ->toContain('control or broken characters');
});

it('refuses symbolic links', function () {
    expect(($this->refusal)(PackageZip::plugin()->symlink('tour-dates/config.json', '/etc/passwd')))
        ->toContain('"tour-dates/config.json" is a symbolic link');
});

it('refuses encrypted entries', function () {
    expect(($this->refusal)(PackageZip::plugin()->encrypt('tour-dates/README.md')))->toContain('is encrypted');
});

it('refuses entries that appear twice with different case', function () {
    $zip = PackageZip::plugin()->add('tour-dates/readme.md', 'second copy');

    expect(($this->refusal)($zip))->toContain('is in the zip more than once');
});

it('refuses files that are not allowed', function (string $name, string $message) {
    expect(($this->refusal)(PackageZip::plugin()->add($name, 'payload')))->toContain($message);
})->with([
    'phar archive' => ['tour-dates/src/tools.phar', 'not allowed (.phar)'],
    'phtml script' => ['tour-dates/src/view.phtml', 'not allowed (.phtml)'],
    'shell script' => ['tour-dates/bin/install.sh', 'not allowed (.sh)'],
    'executable' => ['tour-dates/bin/convert.exe', 'not allowed (.exe)'],
    'htaccess' => ['tour-dates/.htaccess', 'hidden file'],
    'env file' => ['tour-dates/.env', 'hidden file'],
    'hidden folder' => ['tour-dates/.git/config.json', 'hidden file'],
    'no extension' => ['tour-dates/bin/artisan', 'no file extension'],
    'double extension' => ['tour-dates/assets/logo.png.php5', 'not allowed (.php5)'],
]);

it('allows known plain files without an extension', function () {
    $package = $this->inspector->inspect(($this->zip)(PackageZip::plugin()->add('tour-dates/LICENSE', 'MIT License')), ExtensionKind::Plugin);

    expect($package->files)->toHaveKey('LICENSE');
});

it('refuses plugin PHP files outside src and the provider folders', function (string $name) {
    expect(($this->refusal)(PackageZip::plugin()->add($name, '<?php phpinfo();')))
        ->toContain('is a PHP file outside the allowed folders');
})->with([
    'top level' => 'tour-dates/shell.php',
    'public folder' => 'tour-dates/public/index.php',
    'image disguise' => 'tour-dates/assets/logo.jpg.php',
]);

it('allows plugin PHP files in config, routes, migrations and the autoload folders', function () {
    $zip = PackageZip::plugin('tour-dates', manifest: ['autoload' => ['psr-4' => ['Verso\\Plugins\\TourDates\\' => 'src/', 'Verso\\Plugins\\TourDates\\Library\\' => 'lib/']]])
        ->add('tour-dates/config/tour-dates.php', '<?php return [];')
        ->add('tour-dates/routes/admin.php', '<?php')
        ->add('tour-dates/database/migrations/2026_12_01_000000_create_tour_dates_table.php', '<?php')
        ->add('tour-dates/lib/Calendar.php', '<?php');

    $package = $this->inspector->inspect(($this->zip)($zip), ExtensionKind::Plugin);

    expect($package->files)->toHaveKeys(['config/tour-dates.php', 'routes/admin.php', 'lib/Calendar.php']);
});

it('only allows Blade views as PHP in themes', function (string $name, string $message) {
    expect(($this->refusal)(PackageZip::theme()->add($name, '<?php'), ExtensionKind::Theme))->toContain($message);
})->with([
    'plain php view' => ['lighthouse/views/home.php', 'themes may only hold Blade views'],
    'functions file' => ['lighthouse/functions.php', 'themes may only hold Blade views'],
    'blade outside views' => ['lighthouse/assets/layout.blade.php', 'outside the allowed folders'],
]);

it('refuses packages without a manifest at the top', function () {
    $zip = PackageZip::plugin()->remove('tour-dates/verso.json')->add('tour-dates/docs/verso.json', '{}');

    expect(($this->refusal)($zip))->toContain('has no verso.json file at its top level');
});

it('refuses packages with several top folders', function () {
    $zip = PackageZip::plugin()->add('other-plugin/README.md', 'Second plugin');

    expect(($this->refusal)($zip))->toContain('has no verso.json file at its top level');
});

it('refuses a theme zip uploaded as a plugin', function () {
    expect(($this->refusal)(PackageZip::theme()))->toContain('has no verso.json file');
});

it('refuses broken manifests', function (string $contents, string $message) {
    expect(($this->refusal)(PackageZip::plugin()->add('tour-dates/verso.json', $contents)))->toContain($message);
})->with([
    'not json' => ['{"name": "Tour Dates",', 'not valid JSON'],
    'json list' => ['["tour-dates"]', 'must be a JSON object'],
    'bad slug' => ['{"name": "Tour Dates", "slug": "Tour_Dates", "version": "1.0.0", "provider": "Verso\\\\TourDates\\\\Provider"}', 'slug is missing or not valid'],
    'bad version' => ['{"name": "Tour Dates", "slug": "tour-dates", "version": "one", "provider": "Verso\\\\TourDates\\\\Provider"}', 'version field format is invalid'],
    'unknown permission' => ['{"name": "Tour Dates", "slug": "tour-dates", "version": "1.0.0", "provider": "Verso\\\\TourDates\\\\Provider", "permissions": ["root_access"]}', 'permissions.0'],
    'autoload outside' => ['{"name": "Tour Dates", "slug": "tour-dates", "version": "1.0.0", "provider": "Verso\\\\TourDates\\\\Provider", "autoload": {"psr-4": {"Verso\\\\TourDates\\\\": "../app/"}}}', 'folder inside the plugin'],
]);

it('refuses theme manifests with missing fields or a bad slug', function (array $manifest, string $message) {
    $zip = PackageZip::theme()->add('lighthouse/theme.json', json_encode($manifest));

    expect(($this->refusal)($zip, ExtensionKind::Theme))->toContain($message);
})->with([
    'no name' => [['slug' => 'lighthouse', 'version' => '1.0.0'], 'name field is required'],
    'bad version' => [['slug' => 'lighthouse', 'name' => 'Lighthouse', 'version' => '1.0'], 'version field format is invalid'],
    'bad slug' => [['slug' => '../default', 'name' => 'Lighthouse', 'version' => '1.0.0'], 'slug field format is invalid'],
]);

it('refuses manifests bigger than the limit', function () {
    config(['verso.extensions.max_manifest_kb' => 1]);
    $this->inspector = app(PackageInspector::class);

    $zip = PackageZip::plugin('tour-dates', manifest: ['description' => str_repeat('Concerts all over Portugal. ', 60)]);

    expect(($this->refusal)($zip))->toContain('missing or too big');
});

it('refuses zips bigger than the upload limit', function () {
    config(['verso.extensions.max_upload_kb' => 1]);
    $this->inspector = app(PackageInspector::class);

    $zip = PackageZip::plugin()->add('tour-dates/assets/poster.svg', bin2hex(random_bytes(2048)));

    expect(($this->refusal)($zip))->toContain('The package is bigger than 1 KB');
});

it('refuses zip bombs that unpack beyond the limit', function () {
    config(['verso.extensions.max_unpacked_kb' => 64]);
    $this->inspector = app(PackageInspector::class);

    $zip = PackageZip::plugin()->add('tour-dates/assets/padding.txt', str_repeat('A', 200 * 1024));

    expect(($this->refusal)($zip))->toContain('unpacks to more than 64 KB');
});

it('refuses packages with too many files', function () {
    config(['verso.extensions.max_files' => 3]);
    $this->inspector = app(PackageInspector::class);

    expect(($this->refusal)(PackageZip::plugin()->add('tour-dates/CHANGELOG.md', '## 1.0.0')))->toContain('The limit is 3');
});

it('refuses files that are not zip archives', function () {
    $path = $this->workDir.'/fake.zip';
    File::ensureDirectoryExists($this->workDir);
    File::put($path, 'This is a plain text file pretending to be a zip.');

    expect(fn () => $this->inspector->inspect($path, ExtensionKind::Plugin))->toThrow(InvalidPackage::class, 'not a valid zip');
});

it('refuses empty zips and missing files', function () {
    expect(fn () => $this->inspector->inspect($this->workDir.'/missing.zip', ExtensionKind::Plugin))
        ->toThrow(InvalidPackage::class, 'was not found');

    $path = (new PackageZip)->folder('tour-dates/')->build($this->workDir.'/empty.zip');

    expect(fn () => $this->inspector->inspect($path, ExtensionKind::Plugin))->toThrow(InvalidPackage::class);
});

it('lists every problem it finds at once', function () {
    $zip = PackageZip::plugin()->add('../evil.php', 'x')->add('/etc/passwd', 'x')->symlink('tour-dates/link', '/root');

    try {
        $this->inspector->inspect(($this->zip)($zip), ExtensionKind::Plugin);
        $this->fail('The package was accepted.');
    } catch (InvalidPackage $exception) {
        expect($exception->errors)->toHaveCount(3);
    }
});
