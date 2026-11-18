<?php

namespace App\Verso\Site\Sitemap;

use App\Verso\Core\Settings\Settings;

// The sitemap options from the admin, with defaults from config/verso.php.
class SitemapSettings
{
    public const ENABLED = 'sitemap.enabled';

    public const TYPES = 'sitemap.types';

    public const TAXONOMIES = 'sitemap.taxonomies';

    public const EXCLUDED_ENTRIES = 'sitemap.excluded_entries';

    public const INCLUDE_IMAGES = 'sitemap.include_images';

    public const MAX_URLS = 'sitemap.max_urls';

    public const LAST_MODIFIED = 'sitemap.last_modified';

    // Where <lastmod> of an entry comes from.
    public const LAST_MODIFIED_SOURCES = ['updated', 'published'];

    public function __construct(protected Settings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::ENABLED, (bool) config('verso.sitemap.enabled'));
    }

    /**
     * Included content types, or null for every type not left out in config.
     *
     * @return list<string>|null
     */
    public function types(): ?array
    {
        return $this->names(self::TYPES);
    }

    /** @return list<string>|null */
    public function taxonomies(): ?array
    {
        return $this->names(self::TAXONOMIES);
    }

    /** @return list<int> */
    public function excludedEntries(): array
    {
        $value = $this->settings->get(self::EXCLUDED_ENTRIES);

        return array_values(array_map(intval(...), array_filter(is_array($value) ? $value : [], is_numeric(...))));
    }

    public function includeImages(): bool
    {
        return (bool) $this->settings->get(self::INCLUDE_IMAGES, (bool) config('verso.sitemap.include_images'));
    }

    public function maxUrls(): int
    {
        $value = $this->settings->get(self::MAX_URLS);

        return min(max(is_numeric($value) ? (int) $value : (int) config('verso.sitemap.max_urls'), 1), (int) config('verso.sitemap.max_urls_limit'));
    }

    public function lastModified(): string
    {
        $value = $this->settings->get(self::LAST_MODIFIED);

        return in_array($value, self::LAST_MODIFIED_SOURCES, true) ? $value : 'updated';
    }

    /** @return list<string>|null */
    protected function names(string $key): ?array
    {
        $value = $this->settings->get($key);

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : null;
    }
}
