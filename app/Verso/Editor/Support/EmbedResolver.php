<?php

namespace App\Verso\Editor\Support;

// Finds YouTube and Vimeo videos in a URL and builds privacy friendly player URLs for them.
class EmbedResolver
{
    // YouTube video ids are 11 characters long.
    protected const string YOUTUBE_ID = '[A-Za-z0-9_-]{11}';

    public function __construct(
        protected string $youtubeUrl,
        protected string $vimeoUrl,
    ) {}

    public function resolve(string $url): ?Embed
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) preg_replace('/^(www\.|m\.)/i', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        return match ($host) {
            'youtube.com', 'youtube-nocookie.com', 'music.youtube.com' => $this->youtube($this->youtubeIdFromPath($path, $query), $query),
            'youtu.be' => $this->youtube($this->match('#^/('.self::YOUTUBE_ID.')/?$#', $path), $query),
            'vimeo.com', 'player.vimeo.com' => $this->vimeo($path, $query),
            default => null,
        };
    }

    /** @param array<mixed> $query */
    protected function youtubeIdFromPath(string $path, array $query): ?string
    {
        if ($path === '/watch' && is_string($query['v'] ?? null)) {
            return preg_match('#^'.self::YOUTUBE_ID.'$#', $query['v']) === 1 ? $query['v'] : null;
        }

        return $this->match('#^/(?:embed|shorts|live|v)/('.self::YOUTUBE_ID.')/?$#', $path);
    }

    /** @param array<mixed> $query */
    protected function youtube(?string $id, array $query): ?Embed
    {
        if ($id === null) {
            return null;
        }

        $start = $this->seconds($query['t'] ?? $query['start'] ?? null);

        return new Embed('youtube', $id, $this->youtubeUrl.$id.($start > 0 ? '?start='.$start : ''));
    }

    /** @param array<mixed> $query */
    protected function vimeo(string $path, array $query): ?Embed
    {
        if (preg_match('#^/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/)?(\d{1,12})(?:/([0-9a-f]{6,20}))?/?$#i', $path, $match) !== 1) {
            return null;
        }

        $params = ['dnt' => '1'];
        $hash = $match[2] ?? (is_string($query['h'] ?? null) ? $query['h'] : '');

        if ($hash !== '' && preg_match('/^[0-9a-f]{6,20}$/i', $hash) === 1) {
            $params['h'] = $hash;
        }

        return new Embed('vimeo', $match[1], $this->vimeoUrl.$match[1].'?'.http_build_query($params));
    }

    // Reads a start time like "90", "90s" or "1m30s" as seconds.
    protected function seconds(mixed $value): int
    {
        if (! is_string($value) || preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', $value, $match) !== 1) {
            return 0;
        }

        return (int) ($match[1] ?? 0) * 3600 + (int) ($match[2] ?? 0) * 60 + (int) ($match[3] ?? 0);
    }

    protected function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $match) === 1 ? $match[1] : null;
    }
}
