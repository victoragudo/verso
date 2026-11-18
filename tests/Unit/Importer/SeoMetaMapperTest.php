<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Importer\Support\SeoMetaMapper;
use App\Verso\Importer\Wxr\WxrItem;

function seoItem(array $meta, string $excerpt = ''): WxrItem
{
    return new WxrItem(
        id: 10, postType: 'post', title: 'A Weekend in Lisbon', slug: 'a-weekend-in-lisbon', status: 'publish',
        link: 'https://harbourlight.example/a-weekend-in-lisbon/', content: '', excerpt: $excerpt, creator: 'maria.lopes',
        date: '2024-03-18 09:30:00', dateGmt: '2024-03-18 09:30:00', meta: $meta,
    );
}

beforeEach(function () {
    app(Settings::class)->set('site.name', 'Harbourlight Journal');
    $this->mapper = new SeoMetaMapper(app(Settings::class), '|');
});

it('maps Yoast fields and fills title variables', function () {
    $seo = $this->mapper->map(seoItem([
        '_yoast_wpseo_title' => '%%title%% %%sep%% %%sitename%%',
        '_yoast_wpseo_metadesc' => 'Two slow days in Lisbon.',
        '_yoast_wpseo_focuskw' => 'lisbon weekend',
        '_yoast_wpseo_canonical' => 'https://harbourlight.example/lisbon/',
        '_yoast_wpseo_opengraph-title' => 'Lisbon in two days',
        '_yoast_wpseo_opengraph-description' => 'Trams and fado.',
        '_yoast_wpseo_opengraph-image' => 'https://harbourlight.example/tram.jpg',
        '_yoast_wpseo_meta-robots-noindex' => '1',
    ]), 'A Weekend in Lisbon');

    expect($seo)->toBe([
        'title' => 'A Weekend in Lisbon | Harbourlight Journal',
        'description' => 'Two slow days in Lisbon.',
        'focus_keyword' => 'lisbon weekend',
        'canonical' => 'https://harbourlight.example/lisbon/',
        'og_title' => 'Lisbon in two days',
        'og_description' => 'Trams and fado.',
        'og_image' => 'https://harbourlight.example/tram.jpg',
        'noindex' => true,
    ]);
});

it('maps Rank Math fields and robots', function () {
    $seo = $this->mapper->map(seoItem([
        'rank_math_title' => '%title% %sep% Porto coffee guide',
        'rank_math_description' => '%excerpt%',
        'rank_math_focus_keyword' => 'porto coffee',
        'rank_math_robots' => 'a:2:{i:0;s:7:"noindex";i:1;s:8:"nofollow";}',
        'rank_math_facebook_image' => 'https://harbourlight.example/cafe.jpg',
    ], '<p>The best cafés in Porto.</p>'), 'Coffee Along the Douro');

    expect($seo)->toBe([
        'title' => 'Coffee Along the Douro | Porto coffee guide',
        'description' => 'The best cafés in Porto.',
        'focus_keyword' => 'porto coffee',
        'og_image' => 'https://harbourlight.example/cafe.jpg',
        'noindex' => true,
    ]);
});

it('prefers Yoast when both plugins left data', function () {
    $seo = $this->mapper->map(seoItem(['_yoast_wpseo_metadesc' => 'From Yoast', 'rank_math_description' => 'From Rank Math']), 'Lisbon');

    expect($seo['description'])->toBe('From Yoast');
});

it('drops unknown variables and titles that end up empty', function () {
    $seo = $this->mapper->map(seoItem(['_yoast_wpseo_title' => '%%primary_category%%', '_yoast_wpseo_metadesc' => '%%cf_subtitle%% Day trips from Lisbon']), 'Lisbon');

    expect($seo)->toBe(['description' => 'Day trips from Lisbon']);
});

it('does not mark index robots as noindex', function () {
    $seo = $this->mapper->map(seoItem(['rank_math_robots' => 'a:1:{i:0;s:5:"index";}', '_yoast_wpseo_meta-robots-noindex' => '2']), 'Lisbon');

    expect($seo)->toBe([]);
});

it('returns nothing without SEO plugin data', function () {
    expect($this->mapper->map(seoItem(['_edit_last' => '2']), 'Lisbon'))->toBe([]);
});
