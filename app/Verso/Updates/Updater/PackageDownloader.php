<?php

namespace App\Verso\Updates\Updater;

use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\Release;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;

// Downloads a release package and its small checksum and signature files.
class PackageDownloader
{
    // Checksum and signature files are tiny. Anything bigger is refused.
    protected const MAX_TEXT_BYTES = 4096;

    public function __construct(
        protected Filesystem $files,
        protected int $timeout,
        protected int $maxBytes,
    ) {}

    // Save the zip of the release in the folder and return its path.
    public function download(Release $release, string $directory): string
    {
        if ($release->packageSize !== null && $release->packageSize > $this->maxBytes) {
            throw $this->tooBig($release);
        }

        $body = $this->fetch($release->packageUrl, __('the package of Verso :version', ['version' => $release->version]));

        if (strlen($body) > $this->maxBytes) {
            throw $this->tooBig($release);
        }

        $this->files->ensureDirectoryExists($directory, 0755, true);
        $path = $directory.DIRECTORY_SEPARATOR.($release->packageName() ?: 'verso.zip');
        $this->files->put($path, $body);

        return $path;
    }

    // Download a small text file, like the sha256 or signature file.
    public function fetchText(string $url, string $what): string
    {
        $body = $this->fetch($url, $what);

        if (strlen($body) > self::MAX_TEXT_BYTES) {
            throw UpdateFailed::because(__(':file is too big to be valid.', ['file' => ucfirst($what)]));
        }

        return $body;
    }

    protected function fetch(string $url, string $what): string
    {
        try {
            return Http::timeout($this->timeout)->get($url)->throw()->body();
        } catch (ConnectionException|RequestException $exception) {
            throw UpdateFailed::because(__('Could not download :file (:error).', ['file' => $what, 'error' => $exception->getMessage()]));
        }
    }

    protected function tooBig(Release $release): UpdateFailed
    {
        return UpdateFailed::because(__('The package of Verso :version is bigger than :size.', ['version' => $release->version, 'size' => Number::fileSize($this->maxBytes)]));
    }
}
