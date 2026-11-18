<?php

use App\Verso\Updates\Updater\CoreFiles;
use App\Verso\Updates\Updater\PackageUnpacker;
use Illuminate\Filesystem\Filesystem;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create();
    $zip = $this->sandbox->root.'/release.zip';
    file_put_contents($zip, $this->sandbox->package('1.2.0'));
    $this->source = (new PackageUnpacker(new Filesystem))->unpack($zip, $this->sandbox->root.'/unpacked', '1.2.0');
    $this->files = new CoreFiles(
        new Filesystem,
        ['.env', 'storage', 'plugins', 'themes', 'public/uploads/', 'database/database.sqlite'],
        ['themes/default'],
    );
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('plans core paths and leaves out kept paths', function () {
    expect($this->files->plan($this->source))->toBe([
        'VERSION',
        'app',
        'artisan',
        'database/migrations',
        'public/build',
        'public/index.php',
        'routes',
        'themes/default',
        'vendor',
    ]);
});

it('replaces core files and keeps env, storage, plugins, other themes, uploads and the database', function () {
    $paths = $this->files->plan($this->source);
    $databaseBefore = $this->sandbox->read('database/database.sqlite');

    $this->files->backup($this->sandbox->site(), $paths, $this->sandbox->root.'/backup');
    $this->files->install($this->source, $this->sandbox->site(), $paths);

    expect($this->sandbox->read('routes/web.php'))->toContain('routes 1.2.0')
        ->and($this->sandbox->read('VERSION'))->toBe("1.2.0\n")
        ->and($this->sandbox->read('themes/default/theme.json'))->toContain('1.2.0')
        ->and($this->sandbox->read('app/Verso/Updates/Banner.php'))->toContain('banner 1.2.0')
        ->and(file_exists($this->sandbox->site('app/Legacy/OldWidget.php')))->toBeFalse()
        ->and($this->sandbox->read('.env'))->toBe('APP_KEY=base64:site-secret-key')
        ->and($this->sandbox->read('storage/app/notes.txt'))->toBe('notes written by the site')
        ->and($this->sandbox->read('plugins/reading-time/verso.json'))->toBe('{"name":"Reading Time"}')
        ->and($this->sandbox->read('themes/aurora/theme.json'))->toBe('{"name":"Aurora by Lena Fischer"}')
        ->and($this->sandbox->read('public/uploads/2026/11/harbor-sunrise.jpg'))->toBe('jpeg bytes of the harbor')
        ->and($this->sandbox->read('public/favicon-custom.ico'))->toBe('site owner icon')
        ->and($this->sandbox->read('database/database.sqlite'))->toBe($databaseBefore)
        ->and(file_exists($this->sandbox->site('database/migrations/2026_11_16_100000_add_role_to_users_table.php')))->toBeFalse()
        ->and(file_exists($this->sandbox->site('database/migrations/2026_12_01_000000_add_reading_time.php')))->toBeTrue();
});

it('restores the old files and removes paths new in the release', function () {
    $paths = $this->files->plan($this->source);
    $backup = $this->sandbox->root.'/backup';

    $this->files->backup($this->sandbox->site(), $paths, $backup);
    $this->files->install($this->source, $this->sandbox->site(), $paths);
    $this->files->restore($this->sandbox->site(), $backup);

    expect($this->sandbox->read('routes/web.php'))->toContain('routes 0.1.0')
        ->and($this->sandbox->read('VERSION'))->toBe("0.1.0\n")
        ->and($this->sandbox->read('themes/default/theme.json'))->toContain('0.1.0')
        ->and($this->sandbox->read('app/Legacy/OldWidget.php'))->toContain('removed in the next release')
        ->and(file_exists($this->sandbox->site('app/Verso/Updates/Banner.php')))->toBeFalse()
        ->and(file_exists($this->sandbox->site('public/build')))->toBeFalse()
        ->and($this->sandbox->read('public/uploads/2026/11/harbor-sunrise.jpg'))->toBe('jpeg bytes of the harbor')
        ->and($this->files->hasBackup($backup))->toBeTrue();
});

it('restores a backup that stopped half way', function () {
    $backup = $this->sandbox->root.'/backup';
    $this->files->backup($this->sandbox->site(), ['routes'], $backup);
    file_put_contents($backup.'/manifest.json', json_encode(['paths' => ['routes', 'vendor'], 'existed' => ['routes', 'vendor']]));

    $this->files->restore($this->sandbox->site(), $backup);

    expect($this->sandbox->read('routes/web.php'))->toContain('routes 0.1.0')
        ->and($this->sandbox->read('vendor/autoload.php'))->toContain('autoload 0.1.0');
});

it('ignores manifest paths that leave the site folder', function () {
    $backup = $this->sandbox->root.'/backup';
    mkdir($backup.'/files', 0755, true);
    file_put_contents($this->sandbox->root.'/outside.txt', 'must stay');
    file_put_contents($backup.'/manifest.json', json_encode(['paths' => ['../outside.txt'], 'existed' => []]));

    $this->files->restore($this->sandbox->site(), $backup);

    expect(file_get_contents($this->sandbox->root.'/outside.txt'))->toBe('must stay');
});

it('has no backup without a manifest', function () {
    expect($this->files->hasBackup($this->sandbox->root.'/nothing-here'))->toBeFalse();
});
