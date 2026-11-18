<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;

// A box with a silent looping video behind its blocks. It has a pause button and stays still
// for people who ask for less motion; the poster picture shows until the video plays.
class VideoBackgroundBlock extends ModuleBlock implements CacheableBlock, HasChildBlocks
{
    public const NAME = 'video-background';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Video background');
    }

    public function description(): string
    {
        return __('Blocks on top of a silent looping video.');
    }

    public function category(): string
    {
        return 'media';
    }

    public function acceptsChild(string $type): bool
    {
        return $type !== 'column';
    }

    public function fields(): array
    {
        return [
            new BlockField('video', __('Video address (MP4 or WebM)'), BlockFieldType::Url, '', required: true, help: __('A short video from the media library or your site, without sound.')),
            new BlockField('poster', __('Picture shown before the video plays'), BlockFieldType::Media, null),
            new BlockField('overlay', __('Darken the video'), BlockFieldType::Select, 'medium', ['none' => __('No'), 'light' => __('A little'), 'medium' => __('Medium'), 'strong' => __('A lot')], help: __('Makes the text on top easier to read.')),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $video = $this->url($block, 'video');
        $poster = $this->media($block, 'poster');
        $overlay = $this->choice($block, 'overlay', ['none', 'light', 'medium', 'strong'], 'medium');
        $media = $video === null ? '' : '<video class="verso-video-bg__video" src="'.e($video).'" muted loop playsinline preload="metadata" aria-hidden="true" width="1280" height="720"'
            .($poster !== null ? ' poster="'.e($poster['url']).'"' : '').' data-verso-autoplay></video>'
            .'<button type="button" class="verso-video-bg__pause" aria-pressed="false" data-pause-label="'.e(__('Pause the background video')).'" data-play-label="'.e(__('Play the background video')).'" hidden>'.e(__('Pause the background video')).'</button>';

        return '<div class="verso-video-bg verso-video-bg--'.$overlay.'" data-verso-video-bg>'
            .$media.'<div class="verso-video-bg__content">'.$renderer->renderChildren($block).'</div></div>';
    }
}
