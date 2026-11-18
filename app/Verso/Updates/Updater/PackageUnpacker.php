<?php

namespace App\Verso\Updates\Updater;

use App\Verso\Core\Support\Version;
use App\Verso\Updates\Exceptions\UpdateFailed;
use Illuminate\Filesystem\Filesystem;
use ZipArchive;

// Unpacks a release zip safely and checks that it really is the expected Verso version.
class PackageUnpacker
{
    public function __construct(protected Filesystem $files) {}

    // Unpack into the folder and return the folder that holds artisan and VERSION.
    public function unpack(string $zipPath, string $directory, string $expectedVersion): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw UpdateFailed::because(__('The package is not a valid zip file.'));
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $this->checkEntryName((string) $zip->getNameIndex($index));
            }

            $this->files->deleteDirectory($directory);
            $this->files->ensureDirectoryExists($directory, 0755, true);

            if (! $zip->extractTo($directory)) {
                throw UpdateFailed::because(__('The package could not be unpacked.'));
            }
        } finally {
            $zip->close();
        }

        $root = $this->findRoot($directory);
        $this->checkVersion($root, $expectedVersion);

        return $root;
    }

    // Refuse absolute paths, parent folders and odd characters, so nothing lands outside the folder.
    protected function checkEntryName(string $name): void
    {
        $unsafe = $name === ''
            || str_starts_with($name, '/')
            || str_contains($name, '\\')
            || preg_match('/^[A-Za-z]:/', $name) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
            || in_array('..', explode('/', $name), true);

        if ($unsafe) {
            throw UpdateFailed::because(__('The package holds an unsafe file name (:name).', ['name' => $name]));
        }
    }

    // The release may sit at the top of the zip or inside one top folder.
    protected function findRoot(string $directory): string
    {
        if ($this->files->exists($directory.'/artisan')) {
            return $directory;
        }

        $folders = $this->files->directories($directory);

        if (count($folders) === 1 && $this->files->exists($folders[0].'/artisan')) {
            return $folders[0];
        }

        throw UpdateFailed::because(__('The package is not a Verso release (artisan was not found).'));
    }

    protected function checkVersion(string $root, string $expectedVersion): void
    {
        $file = $root.'/VERSION';
        $version = $this->files->exists($file) ? trim($this->files->get($file)) : '';

        if (! Version::isValid($version) || ! Version::parse($version)->equals(Version::parse($expectedVersion))) {
            throw UpdateFailed::because(__('The package holds version ":version", not :expected.', ['version' => $version, 'expected' => $expectedVersion]));
        }
    }
}
