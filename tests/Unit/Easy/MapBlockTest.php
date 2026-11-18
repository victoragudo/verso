<?php

use App\Verso\Easy\Blocks\MapBlock;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Shortcodes\ShortcodeRegistry;
use Tests\Support\EasyFixtures;

function mapOfHarborLane(array $attrs = []): array
{
    return ['id' => 'b_map1', 'type' => 'map', 'attrs' => [
        'latitude' => 41.1405, 'longitude' => -8.6133, 'zoom' => 16, 'label' => 'Harbor Lane Bakery', ...$attrs,
    ], 'content' => '', 'children' => []];
}

it('is known to the block registry and the editor block list', function () {
    expect(app(BlockRegistry::class)->has('map'))->toBeTrue()
        ->and(app(PluginBlockRegistry::class)->has('map'))->toBeTrue()
        ->and(collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'map'))
        ->toMatchArray(['label' => 'Map', 'category' => 'media'])
        ->and(array_column(collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'map')['fields'], 'name'))
        ->toBe(['latitude', 'longitude', 'zoom', 'label']);
});

it('renders an OpenStreetMap frame with a pin and a plain link', function () {
    $html = app(BlockRenderer::class)->render([mapOfHarborLane()]);

    expect($html)->toContain('<iframe src="https://www.openstreetmap.org/export/embed.html?bbox=')
        ->toContain('marker=41.1405%2C-8.6133')
        ->toContain('title="Map of Harbor Lane Bakery"')
        ->toContain('loading="lazy"')
        ->toContain('href="https://www.openstreetmap.org/?mlat=41.1405&amp;mlon=-8.6133#map=16/41.1405/-8.6133"')
        ->toContain('Open a larger map of Harbor Lane Bakery');
});

it('takes the map addresses and height from the config', function () {
    config([
        'verso.easy.map.embed_url' => 'https://maps.harborlane.example/embed',
        'verso.easy.map.link_url' => 'https://maps.harborlane.example/',
        'verso.easy.map.height' => 420,
    ]);

    $html = app(BlockRenderer::class)->render([mapOfHarborLane()]);

    expect($html)->toContain('src="https://maps.harborlane.example/embed?bbox=')
        ->toContain('href="https://maps.harborlane.example/?mlat=')
        ->toContain('height:420px');
});

it('makes the box around the pin smaller when the zoom grows', function () {
    $block = new MapBlock;
    $width = function (string $url): float {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        [$west, , $east] = array_map(floatval(...), explode(',', (string) $query['bbox']));

        return $east - $west;
    };

    expect($width($block->embedUrl(41.1405, -8.6133, 12)))->toBeGreaterThan($width($block->embedUrl(41.1405, -8.6133, 17)));
});

it('renders nothing without valid coordinates', function (array $attrs) {
    expect(app(BlockRenderer::class)->render([mapOfHarborLane($attrs)]))->not->toContain('<iframe');
})->with([
    'no latitude' => [['latitude' => null]],
    'latitude out of range' => [['latitude' => 120]],
    'longitude is text' => [['longitude' => 'west of Porto']],
]);

it('keeps the zoom inside the levels OpenStreetMap knows', function () {
    $html = app(BlockRenderer::class)->render([mapOfHarborLane(['zoom' => 40])]);

    expect($html)->toContain('#map=19/');
});

it('escapes the place name', function () {
    $html = app(BlockRenderer::class)->render([mapOfHarborLane(['label' => 'Bread & <b>Butter</b>'])]);

    expect($html)->toContain('Map of Bread &amp; &lt;b&gt;Butter&lt;/b&gt;')
        ->not->toContain('<b>Butter</b>');
});

it('checks the coordinates when blocks are saved', function () {
    expect(EasyFixtures::blockError([mapOfHarborLane()]))->toBeNull()
        ->and(EasyFixtures::blockError([mapOfHarborLane(['latitude' => 95])]))->not->toBeNull()
        ->and(EasyFixtures::blockError([mapOfHarborLane(['zoom' => 25])]))->not->toBeNull()
        ->and(EasyFixtures::blockError([mapOfHarborLane(['longitude' => null])]))->not->toBeNull();
});

it('is not a shortcode, so [map] stays free for plugins', function () {
    expect(app(ShortcodeRegistry::class)->has('map'))->toBeFalse()
        ->and(collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'map')['shortcode'])->toBeFalse();
});
