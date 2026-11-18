<?php

namespace App\Verso\Easy\Actions;

use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Details\SiteDetails;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Seo\SeoSettings;

// Saves "Your site details": contact details, social profiles, the tagline and the search engine description.
class SaveSiteDetails
{
    public function __construct(protected Settings $settings) {}

    /**
     * @param  array{email?: string|null, phone?: string|null, address?: string|null, hours?: string|null, tagline?: string|null, description?: string|null, social?: list<string>|null}  $values
     */
    public function handle(array $values): void
    {
        $settings = [];

        foreach (SiteDetails::FIELDS as $field => $key) {
            $settings[$key] = trim((string) ($values[$field] ?? ''));
        }

        $settings[SeoSettings::SCHEMA_SAME_AS] = array_values(array_unique(array_filter(
            array_map(fn (mixed $link): string => trim((string) $link), $values['social'] ?? []),
            fn (string $link): bool => $link !== '',
        )));

        $this->settings->setMany($settings);
        SiteSettingsUpdated::dispatch(array_keys($settings));
    }
}
