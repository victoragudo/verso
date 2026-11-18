<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\SafeUrl;
use App\Verso\Editor\Support\BlockData;

// A self hosted video file played with the browser player.
class VideoBlock extends BaseBlock
{
    public function name(): string
    {
        return 'video';
    }

    public function rules(): array
    {
        return [
            'mediaId' => ['nullable', 'integer', 'min:1'],
            'src' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'poster' => ['nullable', 'string', 'max:2048', new SafeUrl],
            'caption' => ['nullable', 'string', 'max:2000'],
            'autoplay' => ['nullable', 'boolean'],
            'loop' => ['nullable', 'boolean'],
            'muted' => ['nullable', 'boolean'],
            'controls' => ['nullable', 'boolean'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $src = $renderer->urls()->clean($block->string('src'));

        if ($src === null) {
            return '';
        }

        $autoplay = $block->bool('autoplay');
        $flags = array_keys(array_filter([
            'controls' => $block->bool('controls', true),
            'autoplay' => $autoplay,
            'loop' => $block->bool('loop'),
            // Browsers only autoplay muted videos.
            'muted' => $autoplay || $block->bool('muted'),
            'playsinline' => true,
        ]));

        $video = '<video'.$this->attribute('src', $src)
            .$this->attribute('poster', $renderer->urls()->clean($block->string('poster')))
            .' '.implode(' ', $flags).' preload="metadata"></video>';

        return '<figure class="verso-video">'.$video.$this->caption($block->string('caption'), $renderer).'</figure>';
    }
}
