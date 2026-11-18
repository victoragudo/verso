<?php

namespace App\Verso\Site\Actions;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Permalinks\PermalinkPattern;
use App\Verso\Site\Permalinks\PermalinkSettings;
use App\Verso\Site\Permalinks\PermalinkValues;

// Saves new URL structures. Old structures and bases are kept, so old links still redirect to the new ones.
class UpdatePermalinks
{
    public function __construct(
        protected Settings $settings,
        protected PermalinkSettings $permalinks,
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected int $previousKept,
    ) {}

    public function handle(PermalinkValues $values): void
    {
        $structures = [];
        $archiveBases = [];
        $taxonomyBases = [];
        $previousStructures = $this->map(PermalinkSettings::PREVIOUS_STRUCTURES);
        $previousArchives = $this->map(PermalinkSettings::PREVIOUS_ARCHIVE_BASES);
        $previousTaxonomies = $this->map(PermalinkSettings::PREVIOUS_TAXONOMY_BASES);

        foreach ($this->types->all() as $type) {
            $old = $this->permalinks->pattern($type)->pattern;
            $new = (new PermalinkPattern($values->structures[$type->name] ?? $old))->pattern;
            $structures[$type->name] = $new;
            $previousStructures[$type->name] = $this->remember($previousStructures[$type->name] ?? [], $old, $new);

            if ($type->hasArchive) {
                $oldBase = $this->permalinks->archiveBase($type);
                $newBase = trim($values->archiveBases[$type->name] ?? $oldBase, '/');
                $archiveBases[$type->name] = $newBase;
                $previousArchives[$type->name] = $this->remember($previousArchives[$type->name] ?? [], $oldBase, $newBase);
            }
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            $oldBase = $this->permalinks->taxonomyBase($taxonomy);
            $newBase = trim($values->taxonomyBases[$taxonomy->name] ?? $oldBase, '/');
            $taxonomyBases[$taxonomy->name] = $newBase;
            $previousTaxonomies[$taxonomy->name] = $this->remember($previousTaxonomies[$taxonomy->name] ?? [], $oldBase, $newBase);
        }

        $saved = [
            PermalinkSettings::STRUCTURES => $structures,
            PermalinkSettings::ARCHIVE_BASES => $archiveBases,
            PermalinkSettings::TAXONOMY_BASES => $taxonomyBases,
            PermalinkSettings::TRAILING_SLASH => $values->trailingSlash,
            PermalinkSettings::LOWERCASE => $values->lowercase,
            PermalinkSettings::PREVIOUS_STRUCTURES => $previousStructures,
            PermalinkSettings::PREVIOUS_ARCHIVE_BASES => $previousArchives,
            PermalinkSettings::PREVIOUS_TAXONOMY_BASES => $previousTaxonomies,
        ];

        // Saving settings empties the page and menu caches. The event refreshes the sitemap.
        $this->settings->setMany($saved);

        SiteSettingsUpdated::dispatch(array_keys($saved));
    }

    /**
     * Adds the old value in front when it changed, drops the new value, keeps the newest ones.
     *
     * @return list<string>
     */
    protected function remember(mixed $list, string $old, string $new): array
    {
        $list = array_values(array_filter(is_array($list) ? $list : [], fn (mixed $value): bool => is_string($value) && $value !== $new));

        if ($old !== $new && $old !== '' && $old !== '/') {
            $list = [$old, ...array_values(array_diff($list, [$old]))];
        }

        return array_slice($list, 0, max($this->previousKept, 0));
    }

    /** @return array<string, mixed> */
    protected function map(string $key): array
    {
        $value = $this->settings->get($key);

        return is_array($value) ? $value : [];
    }
}
