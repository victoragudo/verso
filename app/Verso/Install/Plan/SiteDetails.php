<?php

namespace App\Verso\Install\Plan;

// Site name, tagline, address, language and time zone.
final readonly class SiteDetails
{
    public function __construct(
        public string $name,
        public string $tagline,
        public string $url,
        public string $locale,
        public string $timezone,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['site_name'] ?? '')),
            tagline: trim((string) ($data['tagline'] ?? '')),
            url: rtrim(trim((string) ($data['site_url'] ?? '')), '/'),
            locale: (string) ($data['locale'] ?? 'en'),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
        );
    }
}
