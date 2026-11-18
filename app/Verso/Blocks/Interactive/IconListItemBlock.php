<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasParentBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// One line of an icon list.
class IconListItemBlock extends ModuleBlock implements CacheableBlock, HasParentBlocks
{
    public const NAME = 'icon-list-item';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Icon list item');
    }

    public function description(): string
    {
        return __('One line with an icon.');
    }

    public function parents(): array
    {
        return [IconListBlock::NAME];
    }

    public function fields(): array
    {
        return [
            new BlockField('icon', __('Icon'), BlockFieldType::Icon, 'check', required: true),
            new BlockField('text', __('Text'), BlockFieldType::Text, '', required: true, max: 300),
            new BlockField('url', __('Link'), BlockFieldType::Url, ''),
        ];
    }

    public function assets(): array
    {
        return ['icons'];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $text = e($this->text($block, 'text'));
        $url = $this->url($block, 'url');

        return '<li class="verso-icon-list__item">'.$this->icon($this->text($block, 'icon', 'check'))
            .($url === null ? '<span>'.$text.'</span>' : '<a href="'.e($url).'">'.$text.'</a>').'</li>';
    }
}
