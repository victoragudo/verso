<?php

namespace App\Verso\Importer\Content;

// Remembers imported files by their WordPress id and old URL.
class MediaMap
{
    /** @var array<int, ImportedMedia> */
    protected array $byId = [];

    /** @var array<string, ImportedMedia> */
    protected array $byUrl = [];

    public function add(int $wordpressId, string $oldUrl, ImportedMedia $media): void
    {
        $this->byId[$wordpressId] = $media;

        if ($oldUrl !== '') {
            $this->byUrl[$this->key($oldUrl)] = $media;
        }
    }

    public function byId(int $wordpressId): ?ImportedMedia
    {
        return $this->byId[$wordpressId] ?? null;
    }

    // Also finds resized copies, like "photo-1024x683.jpg" for "photo.jpg".
    public function byUrl(string $url): ?ImportedMedia
    {
        if ($url === '') {
            return null;
        }

        $key = $this->key($url);
        $original = (string) preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/', '', $key);

        return $this->byUrl[$key] ?? $this->byUrl[$original] ?? null;
    }

    // Find by id first, then by URL.
    public function find(?int $wordpressId, string $url): ?ImportedMedia
    {
        return ($wordpressId !== null ? $this->byId($wordpressId) : null) ?? $this->byUrl($url);
    }

    public function count(): int
    {
        return count($this->byId);
    }

    // Compare URLs without scheme, query string or "-scaled" suffix.
    protected function key(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_HOST) ?? '').(string) (parse_url($url, PHP_URL_PATH) ?? '');

        return strtolower((string) preg_replace('/-scaled(?=\.[a-z0-9]+$)/i', '', $path));
    }
}
