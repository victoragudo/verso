<?php

namespace App\Verso\Updates\Feed;

use App\Verso\Core\Support\Version;
use App\Verso\Updates\Enums\UpdateChannel;
use App\Verso\Updates\Exceptions\UpdateFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Reads the release feed (GitHub releases API format) and finds the newest release for a channel.
class ReleaseFeed
{
    /**
     * @param  list<string>  $downloadSchemes
     */
    public function __construct(
        protected string $url,
        protected int $timeout,
        protected string $assetName,
        protected string $checksumSuffix,
        protected string $signatureSuffix,
        protected string $securityMarker,
        protected array $downloadSchemes,
    ) {}

    // Update checks are off when no feed URL is set.
    public function isConfigured(): bool
    {
        return $this->url !== '';
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * Every usable release in the feed, newest first.
     *
     * @return list<Release>
     */
    public function releases(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $data = Http::timeout($this->timeout)
                ->accept('application/vnd.github+json')
                ->get($this->url)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw UpdateFailed::because(__('The release feed could not be reached (:error).', ['error' => $exception->getMessage()]));
        }

        return $this->parse(is_array($data) ? $data : []);
    }

    /**
     * Turn feed data into releases. Drafts, bad tags and releases without a package are skipped.
     *
     * @param  array<mixed>  $data
     * @return list<Release>
     */
    public function parse(array $data): array
    {
        $items = array_is_list($data) ? $data : (array) ($data['releases'] ?? []);
        $releases = [];

        foreach ($items as $item) {
            $release = is_array($item) ? $this->parseRelease($item) : null;

            if ($release !== null) {
                $releases[$release->version] ??= $release;
            }
        }

        $releases = array_values($releases);
        usort($releases, fn (Release $a, Release $b): int => $b->semver()->compare($a->semver()));

        return $releases;
    }

    // The newest release the channel allows, or null when the feed has none.
    /** @param list<Release> $releases */
    public function latestFor(array $releases, UpdateChannel $channel): ?Release
    {
        foreach ($releases as $release) {
            if ($channel === UpdateChannel::Beta || ! $release->preRelease) {
                return $release;
            }
        }

        return null;
    }

    /** @param array<mixed> $item */
    protected function parseRelease(array $item): ?Release
    {
        $tag = $item['tag_name'] ?? null;

        if (($item['draft'] ?? false) === true || ! is_string($tag) || ! Version::isValid($tag)) {
            return null;
        }

        $version = (string) Version::parse($tag);
        $assets = $this->assetsByName($item['assets'] ?? []);
        $packageName = str_replace('{version}', $version, $this->assetName);
        $package = $assets[$packageName] ?? null;

        if ($package === null) {
            return null;
        }

        $name = is_string($item['name'] ?? null) && trim($item['name']) !== '' ? trim($item['name']) : 'Verso '.$version;
        $notes = is_string($item['body'] ?? null) ? $item['body'] : '';

        return new Release(
            version: $version,
            name: $name,
            notes: $notes,
            publishedAt: is_string($item['published_at'] ?? null) ? $item['published_at'] : null,
            preRelease: ($item['prerelease'] ?? false) === true || Version::parse($version)->isPreRelease(),
            security: $this->securityMarker !== '' && Str::contains($name.' '.$notes, $this->securityMarker, ignoreCase: true),
            packageUrl: $package['url'],
            checksumUrl: ($assets[$packageName.$this->checksumSuffix] ?? null)['url'] ?? null,
            signatureUrl: ($assets[$packageName.$this->signatureSuffix] ?? null)['url'] ?? null,
            pageUrl: is_string($item['html_url'] ?? null) && $this->allowedUrl($item['html_url']) ? $item['html_url'] : null,
            packageSize: $package['size'],
        );
    }

    /**
     * Assets with an allowed download URL, by file name.
     *
     * @return array<string, array{url: string, size: int|null}>
     */
    protected function assetsByName(mixed $assets): array
    {
        $byName = [];

        foreach (is_array($assets) ? $assets : [] as $asset) {
            $name = is_array($asset) ? ($asset['name'] ?? null) : null;
            $url = is_array($asset) ? ($asset['browser_download_url'] ?? null) : null;

            if (is_string($name) && is_string($url) && $this->allowedUrl($url)) {
                $byName[$name] = ['url' => $url, 'size' => is_int($asset['size'] ?? null) ? $asset['size'] : null];
            }
        }

        return $byName;
    }

    protected function allowedUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, $this->downloadSchemes, true) && parse_url($url, PHP_URL_HOST) !== null;
    }
}
