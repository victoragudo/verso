<?php

namespace App\Verso\Updates\Feed;

use App\Verso\Core\Support\Version;

// One Verso release from the feed, with the URLs of its package files.
final readonly class Release
{
    public function __construct(
        public string $version,
        public string $name,
        public string $notes,
        public ?string $publishedAt,
        public bool $preRelease,
        public bool $security,
        public string $packageUrl,
        public ?string $checksumUrl,
        public ?string $signatureUrl,
        public ?string $pageUrl = null,
        public ?int $packageSize = null,
    ) {}

    public function semver(): Version
    {
        return Version::parse($this->version);
    }

    // The name of the zip file, taken from its URL.
    public function packageName(): string
    {
        return basename((string) parse_url($this->packageUrl, PHP_URL_PATH));
    }

    /**
     * Plain data for the cache and the updates table.
     *
     * @return array{version: string, name: string, notes: string, published_at: string|null, pre_release: bool, security: bool, package_url: string, checksum_url: string|null, signature_url: string|null, page_url: string|null, package_size: int|null}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'name' => $this->name,
            'notes' => $this->notes,
            'published_at' => $this->publishedAt,
            'pre_release' => $this->preRelease,
            'security' => $this->security,
            'package_url' => $this->packageUrl,
            'checksum_url' => $this->checksumUrl,
            'signature_url' => $this->signatureUrl,
            'page_url' => $this->pageUrl,
            'package_size' => $this->packageSize,
        ];
    }

    // Build a release back from toArray() data. Broken data gives null.
    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $version = $data['version'] ?? null;
        $packageUrl = $data['package_url'] ?? null;

        if (! is_string($version) || ! Version::isValid($version) || ! is_string($packageUrl) || $packageUrl === '') {
            return null;
        }

        return new self(
            version: $version,
            name: is_string($data['name'] ?? null) ? $data['name'] : 'Verso '.$version,
            notes: is_string($data['notes'] ?? null) ? $data['notes'] : '',
            publishedAt: is_string($data['published_at'] ?? null) ? $data['published_at'] : null,
            preRelease: (bool) ($data['pre_release'] ?? false),
            security: (bool) ($data['security'] ?? false),
            packageUrl: $packageUrl,
            checksumUrl: is_string($data['checksum_url'] ?? null) ? $data['checksum_url'] : null,
            signatureUrl: is_string($data['signature_url'] ?? null) ? $data['signature_url'] : null,
            pageUrl: is_string($data['page_url'] ?? null) ? $data['page_url'] : null,
            packageSize: is_int($data['package_size'] ?? null) ? $data['package_size'] : null,
        );
    }
}
