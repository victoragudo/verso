<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// A thin bar with a short message and an optional link, usually at the top of the header.
class AnnouncementBarBlock extends SiteBlock
{
    // Color choices the themes style.
    public const TONES = ['accent', 'dark', 'light'];

    public function name(): string
    {
        return 'announcement-bar';
    }

    public function label(): string
    {
        return __('Announcement bar');
    }

    public function description(): string
    {
        return __('A short message across the top of the page.');
    }

    public function fields(): array
    {
        return [
            new BlockField('text', __('Message'), BlockFieldType::Text, '', help: __('Keep it short, one line on phones.'), required: true, max: 200),
            new BlockField('linkLabel', __('Link text'), BlockFieldType::Text, '', max: 60),
            new BlockField('url', __('Link address'), BlockFieldType::Url, ''),
            new BlockField('tone', __('Color'), BlockFieldType::Select, 'accent', $this->toneOptions()),
        ];
    }

    /** @return array<string, string> */
    protected function toneOptions(): array
    {
        return ['accent' => __('Accent color'), 'dark' => __('Dark'), 'light' => __('Light')];
    }

    protected function html(array $attrs): string
    {
        $text = $this->text($attrs, 'text');

        if ($text === '') {
            return '';
        }

        $tone = in_array($this->text($attrs, 'tone'), self::TONES, true) ? $this->text($attrs, 'tone') : 'accent';
        $url = $this->url($this->text($attrs, 'url'));
        $label = $this->text($attrs, 'linkLabel');
        $link = $url !== null && $label !== '' ? ' '.$this->link($url, $label) : '';

        return '<div class="verso-announcement verso-announcement--'.$tone.'" role="region" aria-label="'.e(__('Announcement')).'"><p>'.e($text).$link.'</p></div>';
    }
}
