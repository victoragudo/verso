<?php

namespace App\Verso\Importer\Support;

use App\Verso\Core\Settings\Settings;
use App\Verso\Importer\Wxr\WxrItem;

// Reads Yoast SEO and Rank Math post meta and gives the Verso "seo" meta.
class SeoMetaMapper
{
    // Verso field => Yoast meta key, Rank Math meta key.
    protected const FIELDS = [
        'title' => ['_yoast_wpseo_title', 'rank_math_title'],
        'description' => ['_yoast_wpseo_metadesc', 'rank_math_description'],
        'focus_keyword' => ['_yoast_wpseo_focuskw', 'rank_math_focus_keyword'],
        'canonical' => ['_yoast_wpseo_canonical', 'rank_math_canonical_url'],
        'og_title' => ['_yoast_wpseo_opengraph-title', 'rank_math_facebook_title'],
        'og_description' => ['_yoast_wpseo_opengraph-description', 'rank_math_facebook_description'],
        'og_image' => ['_yoast_wpseo_opengraph-image', 'rank_math_facebook_image'],
    ];

    // Fields that may hold title variables like %%title%%.
    protected const TEMPLATED = ['title', 'description', 'og_title', 'og_description'];

    public function __construct(protected Settings $settings, protected string $separator = '-') {}

    /**
     * The SEO fields found for an item. Empty when no SEO plugin data exists.
     *
     * @return array<string, string|bool>
     */
    public function map(WxrItem $item, string $title): array
    {
        $seo = [];

        foreach (self::FIELDS as $field => $keys) {
            $value = $this->first($item, $keys);

            if ($value === '') {
                continue;
            }

            $seo[$field] = in_array($field, self::TEMPLATED, true) ? $this->fillVariables($value, $title, $item) : $value;
        }

        $seo = array_filter($seo, fn (string $value): bool => $value !== '');

        if ($this->isNoindex($item)) {
            $seo['noindex'] = true;
        }

        return $seo;
    }

    /** @param list<string> $keys */
    protected function first(WxrItem $item, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) $item->metaValue($key));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    // Yoast stores "1" for noindex. Rank Math stores a serialized list that holds "noindex".
    protected function isNoindex(WxrItem $item): bool
    {
        if ($item->metaValue('_yoast_wpseo_meta-robots-noindex') === '1') {
            return true;
        }

        return preg_match('/"noindex"/', (string) $item->metaValue('rank_math_robots')) === 1;
    }

    // Replace the common Yoast (%%title%%) and Rank Math (%title%) variables and drop unknown ones.
    protected function fillVariables(string $value, string $title, WxrItem $item): string
    {
        $values = [
            'title' => $title,
            'sitename' => (string) $this->settings->get('site.name'),
            'sep' => $this->separator,
            'excerpt' => trim(strip_tags($item->excerpt)),
        ];

        $value = (string) preg_replace_callback(
            '/%%?([a-z_]+)%%?/i',
            fn (array $match): string => $values[strtolower($match[1])] ?? '',
            $value,
        );

        return trim((string) preg_replace('/\s{2,}/', ' ', $value));
    }
}
