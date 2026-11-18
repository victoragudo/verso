<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// A box that stands out: a note, a tip, a warning or an error. The kind is said in words and shown with an icon,
// never only with a color.
class AlertBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'alert';

    public const TONES = ['info' => 'info', 'success' => 'circle-check', 'warning' => 'triangle-alert', 'error' => 'circle-alert'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Alert');
    }

    public function description(): string
    {
        return __('A box that stands out: a note, a tip or a warning.');
    }

    public function category(): string
    {
        return 'text';
    }

    public function fields(): array
    {
        return [
            new BlockField('tone', __('Kind'), BlockFieldType::Select, 'info', ['info' => __('Note'), 'success' => __('Good news'), 'warning' => __('Warning'), 'error' => __('Problem')]),
            new BlockField('title', __('Title'), BlockFieldType::Text, '', max: 120),
            new BlockField('text', __('Text'), BlockFieldType::Textarea, '', required: true),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $tone = $this->choice($block, 'tone', array_keys(self::TONES), 'info');
        $kind = match ($tone) {
            'success' => __('Good news'),
            'warning' => __('Warning'),
            'error' => __('Problem'),
            default => __('Note'),
        };
        $title = $this->text($block, 'title');
        $paragraphs = array_filter(array_map(trim(...), preg_split('/\R{2,}/', $this->text($block, 'text')) ?: []));

        return '<div class="verso-alert verso-alert--'.$tone.'" role="note">'.$this->icon(self::TONES[$tone], '', 'verso-icon verso-alert__icon')
            .'<div class="verso-alert__body"><p class="verso-alert__title"><span class="verso-visually-hidden">'.e($kind).': </span>'.e($title !== '' ? $title : $kind).'</p>'
            .implode('', array_map(fn (string $paragraph): string => '<p>'.nl2br(e($paragraph), false).'</p>', $paragraphs)).'</div></div>';
    }

    public function assets(): array
    {
        return [self::NAME, 'icons'];
    }
}
