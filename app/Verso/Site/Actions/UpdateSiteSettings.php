<?php

namespace App\Verso\Site\Actions;

use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Events\SiteSettingsUpdated;

// Saves the general site settings in one go.
class UpdateSiteSettings
{
    /** @var list<string> */
    public const KEYS = [
        'site.name',
        'site.tagline',
        'site.url',
        'site.locale',
        'site.timezone',
        'reading.posts_per_page',
        'reading.front_page',
        'comments.enabled',
        'content.revisions_limit',
    ];

    public function __construct(protected Settings $settings) {}

    /**
     * Only known keys are saved. Other keys are ignored.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed> the saved values
     */
    public function handle(array $values): array
    {
        $values = array_intersect_key($values, array_flip(self::KEYS));

        $this->settings->setMany($values);

        SiteSettingsUpdated::dispatch(array_keys($values));

        return $values;
    }

    /**
     * The current value of every key, for the settings form.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $values = [];

        foreach (self::KEYS as $key) {
            $values[$key] = $this->settings->get($key);
        }

        return $values;
    }
}
