<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// One icon from the bundled icon set, in the text color. With a link it becomes a named link.
class IconBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'icon';

    public const SIZES = ['small', 'medium', 'large', 'x-large'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Icon');
    }

    public function description(): string
    {
        return __('An icon from the built in set. Its color is the text color.');
    }

    public function category(): string
    {
        return 'media';
    }

    public function fields(): array
    {
        return [
            new BlockField('icon', __('Icon'), BlockFieldType::Icon, 'star', required: true),
            new BlockField('size', __('Size'), BlockFieldType::Select, 'medium', ['small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large'), 'x-large' => __('Extra large')]),
            new BlockField('label', __('What it means'), BlockFieldType::Text, '', help: __('Leave empty when the icon is only decoration.'), max: 120),
            new BlockField('url', __('Link'), BlockFieldType::Url, ''),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $label = $this->text($block, 'label');
        $url = $this->url($block, 'url');
        $svg = $this->icon($this->text($block, 'icon', 'star'), $url === null ? $label : '');

        if ($svg === '') {
            return '';
        }

        $classes = 'verso-icon-block verso-icon-block--'.$this->choice($block, 'size', self::SIZES, 'medium');

        return $url === null
            ? '<span class="'.$classes.'">'.$svg.'</span>'
            : '<a class="'.$classes.'" href="'.e($url).'" aria-label="'.e($label !== '' ? $label : $this->linkName($url)).'">'.$svg.'</a>';
    }

    // A name for a link without a label: the site it goes to, like "instagram.com".
    protected function linkName(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', $host) ?? $host : $url;
    }

    public function assets(): array
    {
        return ['icons'];
    }
}
