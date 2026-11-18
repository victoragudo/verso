<?php

namespace App\Verso\Easy\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockCategory;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// A map with a pin from OpenStreetMap, shown in a frame, with a plain link for people who cannot use the frame.
class MapBlock implements EmbeddableBlock, HasBlockCategory, HasBlockFields, PluginBlock
{
    public const NAME = 'map';

    // Zoom levels OpenStreetMap knows: 1 shows the world, 19 one street.
    public const MIN_ZOOM = 1;

    public const MAX_ZOOM = 19;

    // Map tiles are 256 pixels wide, so one tile covers 360 / 2^zoom degrees.
    protected const TILE_DEGREES = 360;

    // Tiles shown across the frame, which sets how much area the frame shows around the pin.
    protected const TILES_ACROSS = 3;

    // Limits what the map page can do inside our page.
    protected const SANDBOX = 'allow-scripts allow-same-origin allow-popups';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Map');
    }

    public function description(): string
    {
        return __('A map with a pin, from OpenStreetMap, without tracking.');
    }

    public function category(): string
    {
        return 'media';
    }

    // A map is a block only, not a shortcode: [map] stays free for plugins.
    public function shortcode(): bool
    {
        return false;
    }

    public function keyAttribute(): ?string
    {
        return null;
    }

    public function attributeAliases(): array
    {
        return [];
    }

    public function capability(): ?string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new BlockField('latitude', __('Latitude'), BlockFieldType::Number, null, help: __('Like :example. Find it on openstreetmap.org: right click the place and pick "Show address".', ['example' => '40.4168']), required: true, min: -90, max: 90),
            new BlockField('longitude', __('Longitude'), BlockFieldType::Number, null, help: __('Like :example.', ['example' => '-3.7038']), required: true, min: -180, max: 180),
            new BlockField('zoom', __('Zoom'), BlockFieldType::Number, (int) config('verso.easy.map.default_zoom'), help: __('1 shows the world, 19 one street.'), min: self::MIN_ZOOM, max: self::MAX_ZOOM),
            new BlockField('label', __('Place name'), BlockFieldType::Text, '', help: __('Shown in the link under the map, like ":example".', ['example' => 'Harbor Lane Bakery']), max: 120),
        ];
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $latitude = $this->coordinate($attrs['latitude'] ?? null, 90);
        $longitude = $this->coordinate($attrs['longitude'] ?? null, 180);

        if ($latitude === null || $longitude === null) {
            return '';
        }

        $zoom = is_numeric($attrs['zoom'] ?? null) ? (int) $attrs['zoom'] : (int) config('verso.easy.map.default_zoom');
        $zoom = max(self::MIN_ZOOM, min(self::MAX_ZOOM, $zoom));
        $label = is_string($attrs['label'] ?? null) ? trim($attrs['label']) : '';
        $title = $label === '' ? __('Map of the place') : __('Map of :place', ['place' => $label]);
        $link = $label === '' ? __('Open a larger map') : __('Open a larger map of :place', ['place' => $label]);
        $height = max(120, (int) config('verso.easy.map.height'));

        return '<figure class="verso-map">'
            .'<iframe src="'.e($this->embedUrl($latitude, $longitude, $zoom)).'" title="'.e($title).'"'
            .' style="display:block;width:100%;height:'.$height.'px;border:0" loading="lazy" referrerpolicy="no-referrer"'
            .' sandbox="'.self::SANDBOX.'"></iframe>'
            .'<figcaption><a href="'.e($this->linkUrl($latitude, $longitude, $zoom)).'" rel="noopener">'.e($link).'</a></figcaption>'
            .'</figure>';
    }

    // The address of the map frame: a box around the pin and the pin itself.
    public function embedUrl(float $latitude, float $longitude, int $zoom): string
    {
        $half = self::TILE_DEGREES / (2 ** $zoom) * self::TILES_ACROSS / 2;
        $box = [
            $this->number(max(-180, $longitude - $half)),
            $this->number(max(-90, $latitude - $half / 2)),
            $this->number(min(180, $longitude + $half)),
            $this->number(min(90, $latitude + $half / 2)),
        ];

        return (string) config('verso.easy.map.embed_url').'?'.http_build_query([
            'bbox' => implode(',', $box),
            'layer' => 'mapnik',
            'marker' => $this->number($latitude).','.$this->number($longitude),
        ]);
    }

    // The address of the full map page with the same pin.
    public function linkUrl(float $latitude, float $longitude, int $zoom): string
    {
        $lat = $this->number($latitude);
        $lon = $this->number($longitude);

        return (string) config('verso.easy.map.link_url').'?'.http_build_query(['mlat' => $lat, 'mlon' => $lon])."#map={$zoom}/{$lat}/{$lon}";
    }

    protected function coordinate(mixed $value, int $limit): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value >= -$limit && $value <= $limit ? $value : null;
    }

    // A short decimal text without trailing zeros, like "40.4168".
    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
