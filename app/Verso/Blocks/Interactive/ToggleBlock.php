<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// One part that opens and closes, like "Show the ingredients". A plain <details>, no JavaScript.
class ToggleBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks
{
    public const NAME = 'toggle';

    // Accordions and toggles share their styles.
    public const ASSET = 'disclosure';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Toggle');
    }

    public function description(): string
    {
        return __('A title that opens to show more blocks.');
    }

    public function acceptsChild(string $type): bool
    {
        return $type !== 'column';
    }

    public function fields(): array
    {
        return [
            new BlockField('title', __('Title'), BlockFieldType::Text, '', required: true, max: 200),
            new BlockField('open', __('Open when the page loads'), BlockFieldType::Toggle, false),
        ];
    }

    public function assets(): array
    {
        return [self::ASSET];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        return self::details($this->text($block, 'title', __('More')), $renderer->renderChildren($block), $block->bool('open'));
    }

    // A <details> with its summary. The answer sits in its own box, so styles never touch the details element.
    public static function details(string $title, string $body, bool $open, ?string $group = null): string
    {
        return '<details class="verso-disclosure"'.($open ? ' open' : '').($group !== null ? ' name="'.e($group).'"' : '').'>'
            .'<summary class="verso-disclosure__summary">'.e($title).'</summary>'
            .'<div class="verso-disclosure__body">'.$body.'</div></details>';
    }
}
