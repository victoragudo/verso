<?php

namespace App\Verso\Site\Permalinks;

use App\Verso\Content\ContentType;
use App\Verso\Content\Taxonomy;
use App\Verso\Core\Settings\Settings;

// Reads the saved URL structures. Types and taxonomies without a saved value use their code defaults.
class PermalinkSettings
{
    public const STRUCTURES = 'permalinks.structures';

    public const ARCHIVE_BASES = 'permalinks.archive_bases';

    public const TAXONOMY_BASES = 'permalinks.taxonomy_bases';

    public const TRAILING_SLASH = 'permalinks.trailing_slash';

    public const LOWERCASE = 'permalinks.lowercase';

    public const PREVIOUS_STRUCTURES = 'permalinks.previous_structures';

    public const PREVIOUS_ARCHIVE_BASES = 'permalinks.previous_archive_bases';

    public const PREVIOUS_TAXONOMY_BASES = 'permalinks.previous_taxonomy_bases';

    public function __construct(protected Settings $settings) {}

    // The default structure: the route base of the type, then the slug.
    public static function defaultPattern(ContentType $type): string
    {
        return PermalinkPattern::normalize(trim($type->routeBase, '/').'/{slug}');
    }

    public function pattern(ContentType $type): PermalinkPattern
    {
        $saved = $this->map(self::STRUCTURES)[$type->name] ?? null;

        return new PermalinkPattern(is_string($saved) && $saved !== '' ? $saved : self::defaultPattern($type));
    }

    // The archive path of a type, like "blog". Empty when the type has no archive.
    public function archiveBase(ContentType $type): string
    {
        if (! $type->hasArchive) {
            return '';
        }

        $saved = $this->map(self::ARCHIVE_BASES)[$type->name] ?? null;

        return trim(is_string($saved) ? $saved : $type->routeBase, '/');
    }

    public function taxonomyBase(Taxonomy $taxonomy): string
    {
        $saved = $this->map(self::TAXONOMY_BASES)[$taxonomy->name] ?? null;

        return trim(is_string($saved) && $saved !== '' ? $saved : $taxonomy->routeBase, '/');
    }

    public function trailingSlash(): bool
    {
        return (bool) $this->settings->get(self::TRAILING_SLASH);
    }

    public function lowercase(): bool
    {
        return (bool) $this->settings->get(self::LOWERCASE);
    }

    /** @return list<PermalinkPattern> older structures of a type, newest first */
    public function previousPatterns(ContentType $type): array
    {
        return array_map(fn (string $pattern): PermalinkPattern => new PermalinkPattern($pattern), $this->previous(self::PREVIOUS_STRUCTURES, $type->name));
    }

    /** @return list<string> */
    public function previousArchiveBases(ContentType $type): array
    {
        return $this->previous(self::PREVIOUS_ARCHIVE_BASES, $type->name);
    }

    /** @return list<string> */
    public function previousTaxonomyBases(Taxonomy $taxonomy): array
    {
        return $this->previous(self::PREVIOUS_TAXONOMY_BASES, $taxonomy->name);
    }

    /** @return list<string> */
    protected function previous(string $key, string $name): array
    {
        $values = $this->map($key)[$name] ?? [];

        return array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
    }

    /** @return array<string, mixed> */
    protected function map(string $key): array
    {
        $value = $this->settings->get($key);

        return is_array($value) ? $value : [];
    }
}
