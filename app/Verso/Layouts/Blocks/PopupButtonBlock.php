<?php

namespace App\Verso\Layouts\Blocks;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Layouts\LayoutKind;
use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// A button that opens a popup made in the theme builder, like "Book a table". The popup must show on the page
// (its rules match); set its trigger to "On a click" to show it only from buttons.
class PopupButtonBlock extends ModuleBlock
{
    public const NAME = 'popup-button';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Popup button');
    }

    public function description(): string
    {
        return __('A button that opens a popup from the theme builder.');
    }

    public function fields(): array
    {
        $popups = [];

        foreach (SiteLayout::query()->where('kind', LayoutKind::Popup->value)->orderBy('name')->get(['id', 'name']) as $popup) {
            $popups[(string) $popup->id] = $popup->name;
        }

        return [
            new BlockField('label', __('Button text'), BlockFieldType::Text, '', required: true, max: 80),
            new BlockField('popup', __('Popup'), BlockFieldType::Select, array_key_first($popups) ?? '', $popups === [] ? ['' => __('Make a popup in Design, Theme builder first')] : $popups, required: true),
        ];
    }

    public function assets(): array
    {
        return ['modal'];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $popup = $this->text($block, 'popup');

        if (! ctype_digit($popup)) {
            return '';
        }

        return '<button type="button" class="verso-modal__open verso-popup-button" data-verso-open-popup="'.(int) $popup.'" aria-haspopup="dialog">'.e($this->text($block, 'label', __('Open'))).'</button>';
    }
}
