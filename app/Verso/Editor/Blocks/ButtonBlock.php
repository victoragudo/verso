<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\SafeUrl;
use App\Verso\Editor\Support\BlockData;

// A call to action link that looks like a button.
class ButtonBlock extends BaseBlock
{
    // Looks a theme can style.
    public const array STYLES = ['primary', 'secondary', 'outline'];

    public function name(): string
    {
        return 'button';
    }

    public function rules(): array
    {
        return [
            'url' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'label' => ['nullable', 'string', 'max:200'],
            'style' => ['nullable', 'string', 'in:'.implode(',', self::STYLES)],
            'newTab' => ['nullable', 'boolean'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $url = $renderer->urls()->clean($block->string('url'));
        $label = trim($block->string('label'));

        if ($url === null || $label === '') {
            return '';
        }

        $style = in_array($block->string('style'), self::STYLES, true) ? $block->string('style') : self::STYLES[0];
        $target = $block->bool('newTab') ? ' target="_blank" rel="noopener noreferrer"' : '';

        return '<div class="verso-buttons"><a'.$this->attribute('class', "verso-button verso-button--{$style}")
            .$this->attribute('href', $url).$target.'>'.e($label).'</a></div>';
    }
}
