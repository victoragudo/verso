<?php

namespace App\Verso\Extensions\Registry;

use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;

// Reads the registry index and downloads packages, checking their sha256 sum.
class RegistryClient
{
    /**
     * @param  list<string>  $downloadSchemes
     */
    public function __construct(
        protected Cache $cache,
        protected Filesystem $files,
        protected string $url,
        protected int $timeout,
        protected int $cacheTtl,
        protected string $cacheKey,
        protected array $downloadSchemes,
        protected int $maxDownloadBytes,
    ) {}

    // The Browse tab only shows when a registry URL is set.
    public function isConfigured(): bool
    {
        return $this->url !== '';
    }

    /**
     * Every valid entry of the index. Broken entries are skipped.
     *
     * @return list<RegistryEntry>
     */
    public function entries(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        /** @var array<mixed> $data */
        $data = $this->cache->remember($this->cacheKey, $this->cacheTtl, fn (): array => $this->fetchIndex());

        $items = array_is_list($data) ? $data : (array) ($data['extensions'] ?? []);
        $entries = [];

        foreach ($items as $item) {
            $entry = is_array($item) ? RegistryEntry::fromArray($item, $this->downloadSchemes) : null;

            if ($entry !== null) {
                $entries[$entry->kind->value.':'.$entry->slug] ??= $entry;
            }
        }

        return array_values($entries);
    }

    public function find(ExtensionKind $kind, string $slug): ?RegistryEntry
    {
        foreach ($this->entries() as $entry) {
            if ($entry->kind === $kind && $entry->slug === $slug) {
                return $entry;
            }
        }

        return null;
    }

    // Read the index again on the next call.
    public function forget(): void
    {
        $this->cache->forget($this->cacheKey);
    }

    // Download a package to the given file. The file is only written when the sha256 sum matches.
    public function download(RegistryEntry $entry, string $destination): void
    {
        try {
            $response = Http::timeout($this->timeout)->get($entry->downloadUrl)->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw ExtensionInstallFailed::because(__(':name could not be downloaded (:error).', ['name' => $entry->name, 'error' => $exception->getMessage()]));
        }

        $body = $response->body();

        if (strlen($body) > $this->maxDownloadBytes) {
            throw ExtensionInstallFailed::because(__(':name is bigger than :size.', ['name' => $entry->name, 'size' => Number::fileSize($this->maxDownloadBytes)]));
        }

        if (! hash_equals($entry->sha256, hash('sha256', $body))) {
            throw ExtensionInstallFailed::because(__('The download of :name does not match its sha256 sum, so it was not installed.', ['name' => $entry->name]));
        }

        $this->files->ensureDirectoryExists(dirname($destination), 0755, true);
        $this->files->put($destination, $body);
    }

    /**
     * @return array<mixed>
     */
    protected function fetchIndex(): array
    {
        try {
            $data = Http::timeout($this->timeout)->acceptJson()->get($this->url)->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            throw ExtensionInstallFailed::because(__('The registry could not be reached (:error).', ['error' => $exception->getMessage()]));
        }

        return is_array($data) ? $data : [];
    }
}
