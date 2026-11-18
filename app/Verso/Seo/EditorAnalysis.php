<?php

namespace App\Verso\Seo;

use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Seo\SeoSettings;

// What the SEO card of the editor needs: the limits of the checks and how titles and links look in results.
class EditorAnalysis
{
    // config key under verso.seo.analysis => prop name of the analysis engine.
    protected const KEYS = [
        'title_min' => 'titleMin',
        'title_max' => 'titleMax',
        'description_min' => 'descriptionMin',
        'description_max' => 'descriptionMax',
        'min_words' => 'minWords',
        'keyphrase_max_words' => 'keyphraseMaxWords',
        'keyphrase_density_min' => 'keyphraseDensityMin',
        'keyphrase_density_max' => 'keyphraseDensityMax',
        'slug_max_words' => 'slugMaxWords',
        'sentence_max_words' => 'sentenceMaxWords',
        'long_sentences_max_percent' => 'longSentencesMaxPercent',
        'paragraph_max_words' => 'paragraphMaxWords',
        'subheading_max_words' => 'subheadingMaxWords',
        'passive_max_percent' => 'passiveMaxPercent',
        'transition_min_percent' => 'transitionMinPercent',
        'good_score' => 'goodScore',
        'ok_score' => 'okScore',
    ];

    public function __construct(
        protected Settings $settings,
        protected SeoSettings $seo,
    ) {}

    /** @return array<string, mixed> */
    public function props(): array
    {
        $config = [];

        foreach (self::KEYS as $key => $name) {
            $config[$name] = (float) config('verso.seo.analysis.'.$key);
        }

        $siteUrl = (string) $this->settings->get('site.url');

        return [
            'config' => $config,
            'debounceMs' => (int) config('verso.seo.analysis.debounce_ms'),
            'site' => [
                'name' => (string) $this->settings->get('site.name'),
                'tagline' => trim((string) $this->settings->get('site.tagline')),
                'url' => $siteUrl,
                'host' => (string) (parse_url($siteUrl, PHP_URL_HOST) ?: parse_url((string) config('app.url'), PHP_URL_HOST)),
                'locale' => (string) $this->settings->get('site.locale'),
                'titleFormat' => $this->seo->titleFormat(),
                'separator' => $this->seo->separatorSymbol(),
                'defaultDescription' => $this->seo->defaultDescription(),
                'defaultImage' => $this->seo->defaultImageUrl(),
                'twitterSite' => (string) config('verso.seo.twitter_site'),
            ],
            'keyphraseMaxLength' => (int) config('verso.seo.keyphrase_max_length'),
        ];
    }
}
