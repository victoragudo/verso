<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Icons\IconLibrary;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// A button that opens a modal dialog with any blocks inside. Native <dialog>: focus, Escape and the
// backdrop are handled by the browser. Without JavaScript the content shows under the button.
class ModalBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks
{
    public const NAME = 'modal';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Modal');
    }

    public function description(): string
    {
        return __('A button that opens a dialog with blocks inside.');
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function fields(): array
    {
        return [
            new BlockField('button', __('Button text'), BlockFieldType::Text, '', required: true, max: 80),
            new BlockField('title', __('Dialog title'), BlockFieldType::Text, '', required: true, max: 120),
            new BlockField('size', __('Width'), BlockFieldType::Select, 'medium', ['small' => __('Small'), 'medium' => __('Medium'), 'large' => __('Large')]),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $id = $this->domId($block, 'dialog');

        return '<div class="verso-modal" data-verso-modal>'
            .'<button type="button" class="verso-modal__open" commandfor="'.$id.'" command="show-modal" data-opens="'.$id.'" aria-haspopup="dialog">'.e($this->text($block, 'button', __('Open'))).'</button>'
            .self::dialog($id, $this->text($block, 'title'), $renderer->renderChildren($block), $this->choice($block, 'size', ['small', 'medium', 'large'], 'medium'))
            .'</div>';
    }

    // A dialog with a title and a close button. Popups use it too.
    public static function dialog(string $id, string $title, string $body, string $size = 'medium', string $extraClass = ''): string
    {
        return '<dialog class="verso-dialog verso-dialog--'.e($size).($extraClass !== '' ? ' '.e($extraClass) : '').'" id="'.e($id).'" aria-labelledby="'.e($id).'-title" closedby="any">'
            .'<div class="verso-dialog__head"><h2 class="verso-dialog__title" id="'.e($id).'-title">'.e($title).'</h2>'
            .'<button type="button" class="verso-dialog__close" commandfor="'.e($id).'" command="close" data-closes autofocus><span class="verso-visually-hidden">'.e(__('Close')).'</span>'
            .app(IconLibrary::class)->svg('x').'</button></div>'
            .'<div class="verso-dialog__body">'.$body.'</div></dialog>';
    }
}
