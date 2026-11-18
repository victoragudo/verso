<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;

// Share links for the current page. Plain links to each network, no social scripts and no tracking,
// plus a "Copy link" button and the device share sheet where the browser has one.
class SocialShareBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'social-share';

    // Network => share address with {url} and {title}.
    public const NETWORKS = [
        'x' => 'https://x.com/intent/post?url={url}&text={title}',
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
        'whatsapp' => 'https://wa.me/?text={title}%20{url}',
        'telegram' => 'https://t.me/share/url?url={url}&text={title}',
        'pinterest' => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
        'email' => 'mailto:?subject={title}&body={url}',
    ];

    public const LABELS = ['x' => 'X', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'pinterest' => 'Pinterest'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Share buttons');
    }

    public function description(): string
    {
        return __('Links to share this page, without tracking scripts.');
    }

    public function fields(): array
    {
        $fields = [new BlockField('heading', __('Text before the links'), BlockFieldType::Text, '', help: __('Like "Share this recipe".'), max: 80)];

        foreach (array_keys(self::NETWORKS) as $network) {
            $fields[] = new BlockField($network, $network === 'email' ? __('Email') : self::LABELS[$network], BlockFieldType::Toggle, in_array($network, ['x', 'facebook', 'linkedin', 'email'], true));
        }

        $fields[] = new BlockField('copy', __('Copy link button'), BlockFieldType::Toggle, true);
        $fields[] = new BlockField('native', __('Share button of the device'), BlockFieldType::Toggle, true, help: __('Only shows on phones and browsers that have one.'));

        return $fields;
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $entry = app(RenderContext::class)->entry();
        $url = $entry?->url() ?? url()->current();
        $title = $entry->title ?? '';
        $links = '';

        foreach (self::NETWORKS as $network => $pattern) {
            if (! $block->bool($network, in_array($network, ['x', 'facebook', 'linkedin', 'email'], true))) {
                continue;
            }

            $href = str_replace(['{url}', '{title}'], [rawurlencode($url), rawurlencode($title)], $pattern);
            $name = $network === 'email' ? __('Email') : self::LABELS[$network];
            $links .= '<li><a class="verso-share__link verso-share__link--'.$network.'" href="'.e($href).'"'.($network === 'email' ? '' : ' target="_blank" rel="noopener noreferrer"').'>'
                .e(__('Share on :network', ['network' => $name])).'</a></li>';
        }

        if ($block->bool('copy', true)) {
            $links .= '<li class="verso-share__copy"><button type="button" class="verso-share__link" data-verso-copy="'.e($url).'" data-done="'.e(__('Link copied')).'" data-failed="'.e(__('Could not copy the link')).'" hidden>'
                .$this->icon('link').e(__('Copy link')).'</button><span class="verso-share__tip" role="status"></span></li>';
        }

        if ($block->bool('native', true)) {
            $links .= '<li><button type="button" class="verso-share__link" data-verso-native-share data-url="'.e($url).'" data-title="'.e($title).'" hidden>'.$this->icon('share-2').e(__('Share')).'</button></li>';
        }

        if ($links === '') {
            return '';
        }

        $heading = $this->text($block, 'heading');

        return '<div class="verso-share">'.($heading !== '' ? '<p class="verso-share__heading">'.e($heading).'</p>' : '')
            .'<ul class="verso-share__list"'.($heading !== '' ? '' : ' aria-label="'.e(__('Share this page')).'"').'>'.$links.'</ul></div>';
    }
}
