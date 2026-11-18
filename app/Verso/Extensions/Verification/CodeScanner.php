<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Scanners\AssetScanner;
use App\Verso\Extensions\Verification\Scanners\PhpScanner;
use Illuminate\Support\Number;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

// Walks a plugin or theme folder and runs the PHP, Blade and JavaScript scanners on each file.
class CodeScanner
{
    // Folders that are never scanned.
    protected const SKIPPED_FOLDERS = ['node_modules', '.git'];

    public const BLADE_SUFFIX = '.blade.php';

    public const KIND_BLADE = 'blade';

    public const KIND_PHP = 'php';

    public const KIND_SCRIPT = 'script';

    /**
     * @param  list<string>  $scriptExtensions
     */
    public function __construct(
        protected PhpScanner $php,
        protected AssetScanner $assets,
        protected int $maxFileBytes,
        protected array $scriptExtensions,
    ) {}

    /**
     * Scan every PHP, Blade and JavaScript file. Returns how many files were scanned.
     * $each gets the path, kind and code of every scanned file, for extra rules.
     *
     * @param  (callable(string, string, string): void)|null  $each
     */
    public function scan(string $path, ScanResult $scan, ?callable $each = null): int
    {
        $scanned = 0;

        foreach ($this->files($path) as $relative => $file) {
            $kind = $this->kindOf($relative);

            if ($kind === null) {
                continue;
            }

            if ($file->getSize() > $this->maxFileBytes) {
                $scan->add('scan.file-too-large', Severity::Medium, message: 'The file is bigger than :size and was not scanned.', file: $relative, replace: ['size' => Number::fileSize($this->maxFileBytes)]);

                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            $scanned++;

            match ($kind) {
                self::KIND_BLADE => $this->assets->scanBlade($relative, $code, $scan),
                self::KIND_PHP => $this->php->scan($relative, $code, $scan),
                default => $this->assets->scanScript($relative, $code, $scan),
            };

            if ($each !== null) {
                $each($relative, $kind, $code);
            }
        }

        return $scanned;
    }

    public function maxFileBytes(): int
    {
        return $this->maxFileBytes;
    }

    // "php", "blade", "script" or null for files that are not scanned.
    public function kindOf(string $relative): ?string
    {
        if (str_ends_with($relative, self::BLADE_SUFFIX)) {
            return self::KIND_BLADE;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'php' => self::KIND_PHP,
            in_array($extension, $this->scriptExtensions, true) => self::KIND_SCRIPT,
            default => null,
        };
    }

    /**
     * Every regular file of the folder, by path with forward slashes, sorted by path.
     *
     * @return array<string, SplFileInfo>
     */
    public function files(string $path): array
    {
        $path = rtrim($path, '/\\');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        $files = [];

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            $relative = substr($file->getPathname(), strlen($path) + 1);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);

            if ($file->isFile() && ! $file->isLink() && array_intersect($parts, self::SKIPPED_FOLDERS) === []) {
                $files[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = $file;
            }
        }

        ksort($files);

        return $files;
    }
}
