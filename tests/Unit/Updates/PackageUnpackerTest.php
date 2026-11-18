<?php

use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Updater\PackageUnpacker;
use Illuminate\Filesystem\Filesystem;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create();
    $this->unpacker = new PackageUnpacker(new Filesystem);
});

afterEach(function () {
    $this->sandbox->destroy();
});

function writeZip(UpdateSandbox $sandbox, string $bytes): string
{
    $path = $sandbox->root.'/release.zip';
    file_put_contents($path, $bytes);

    return $path;
}

function zipWith(UpdateSandbox $sandbox, array $files): string
{
    $path = $sandbox->root.'/crafted.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

it('unpacks a release inside one top folder', function () {
    $zip = writeZip($this->sandbox, $this->sandbox->package('1.2.0'));

    $root = $this->unpacker->unpack($zip, $this->sandbox->root.'/unpacked', '1.2.0');

    expect($root)->toBe($this->sandbox->root.'/unpacked/verso-1.2.0')
        ->and(file_get_contents($root.'/routes/web.php'))->toContain('routes 1.2.0');
});

it('unpacks a release at the top of the zip', function () {
    $zip = writeZip($this->sandbox, $this->sandbox->package('1.2.0', topFolder: false));

    expect($this->unpacker->unpack($zip, $this->sandbox->root.'/unpacked', 'v1.2.0'))->toBe($this->sandbox->root.'/unpacked');
});

it('refuses a package with another version inside', function () {
    $zip = writeZip($this->sandbox, $this->sandbox->package('1.1.0'));

    $this->unpacker->unpack($zip, $this->sandbox->root.'/unpacked', '1.2.0');
})->throws(UpdateFailed::class, 'holds version "1.1.0", not 1.2.0');

it('refuses a zip that is not a Verso release', function () {
    $zip = zipWith($this->sandbox, ['README.md' => '# Holiday photos']);

    $this->unpacker->unpack($zip, $this->sandbox->root.'/unpacked', '1.2.0');
})->throws(UpdateFailed::class, 'not a Verso release');

it('refuses a file that is not a zip', function () {
    $zip = writeZip($this->sandbox, 'plain text pretending to be a zip');

    $this->unpacker->unpack($zip, $this->sandbox->root.'/unpacked', '1.2.0');
})->throws(UpdateFailed::class, 'not a valid zip file');

it('refuses unsafe file names before anything is written', function (string $name) {
    $zip = zipWith($this->sandbox, ['artisan' => '<?php', 'VERSION' => '1.2.0', $name => 'payload']);

    expect(fn () => $this->unpacker->unpack($zip, $this->sandbox->root.'/unpacked', '1.2.0'))
        ->toThrow(UpdateFailed::class, 'unsafe file name');
    expect(is_dir($this->sandbox->root.'/unpacked'))->toBeFalse();
})->with(['../../escape.php', '/etc/cron.d/verso', 'public/..\\index.php', 'C:/Windows/evil.php']);
