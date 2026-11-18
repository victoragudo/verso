<?php

namespace App\Verso\Editor\Styles;

use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\Shortcodes\Usage\UsageIndex;
use App\Verso\Site\Models\SitePart;
use App\Verso\Theme\Cache\PageCache;

// After a style preset changes, only the pages that use it are drawn again: entries come from the usage index,
// and when a header, footer or layout uses it every page goes. The block cache key holds the preset styles,
// so cached entry HTML is never stale.
class StylePresetInvalidator
{
    public function __construct(
        protected EmbedInvalidator $embeds,
        protected PageCache $pages,
        protected StylePresets $presets,
    ) {}

    public function handle(StylePresetChanged $event): void
    {
        $this->embeds->changed(UsageIndex::PRESET, $event->key);

        if ($this->sharedPartsUse($event->key)) {
            $this->pages->flush();
        }
    }

    protected function sharedPartsUse(string $key): bool
    {
        foreach ([SitePart::query()->pluck('published_blocks'), SiteLayout::query()->pluck('published_blocks')] as $lists) {
            foreach ($lists as $blocks) {
                if (is_array($blocks) && in_array($key, $this->presets->used($blocks), true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
