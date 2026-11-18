<?php

namespace App\Verso\Extensions\Packages;

use App\Verso\Extensions\Exceptions\InvalidPackage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Number;
use ZipArchive;

// Unpacks the checked files of a package, one by one, into an empty folder.
class PackageExtractor
{
    // Bytes copied per read while unpacking.
    protected const CHUNK_BYTES = 65536;

    public function __construct(
        protected PackageRules $rules,
        protected Filesystem $files,
    ) {}

    // Write every file of the package into the target folder. The folder must not exist yet.
    public function extract(string $zipPath, Package $package, string $target): void
    {
        if (file_exists($target)) {
            throw InvalidPackage::because(__('The folder to unpack into already exists.'));
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw InvalidPackage::because(__('The file is not a valid zip archive.'));
        }

        try {
            $this->files->ensureDirectoryExists($target, 0755, true);
            $written = 0;

            foreach ($package->files as $path => $index) {
                $written += $this->extractFile($zip, $index, $path, $target.DIRECTORY_SEPARATOR.$path, $this->rules->maxUnpackedBytes - $written);
            }
        } catch (InvalidPackage $exception) {
            $this->files->deleteDirectory($target);

            throw $exception;
        } finally {
            $zip->close();
        }
    }

    // Copy one entry with a byte budget, so a zip that lies about its sizes cannot fill the disk.
    protected function extractFile(ZipArchive $zip, int $index, string $entryName, string $destination, int $budget): int
    {
        $this->files->ensureDirectoryExists(dirname($destination), 0755, true);

        $input = $zip->getStreamIndex($index);
        $output = fopen($destination, 'xb');

        if ($input === false || $output === false) {
            throw InvalidPackage::because(__('":name" could not be unpacked.', ['name' => $entryName]));
        }

        $written = 0;

        try {
            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw InvalidPackage::because(__('":name" could not be read.', ['name' => $entryName]));
                }

                $written += strlen($chunk);

                if ($written > $budget) {
                    throw InvalidPackage::because(__('The package unpacks to more than :size.', ['size' => Number::fileSize($this->rules->maxUnpackedBytes)]));
                }

                fwrite($output, $chunk);
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        return $written;
    }
}
