<?php

namespace App\Verso\Library;

use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Library\Blocks\SyncedBlock;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\Site\Models\SitePart;
use App\Verso\Theme\Cache\PageCache;

// After a synced block changes: the pages that use it are drawn again. Entries are found with the usage index;
// headers, footers and layouts are shared by many pages, so when one of them holds it the whole page cache goes.
class LibraryInvalidator
{
    public function __construct(protected EmbedInvalidator $embeds, protected PageCache $pages, protected SyncedBlock $synced) {}

    public function handle(LibraryChanged $event): void
    {
        $this->synced->forget();

        if ($event->kind !== 'synced') {
            return;
        }

        $this->embeds->changed(SyncedBlock::NAME, (string) $event->itemId);

        if ($this->sharedPartsUse($event->itemId)) {
            $this->pages->flush();
        }
    }

    protected function sharedPartsUse(int $id): bool
    {
        $needle = '"ref":"'.$id.'"';
        $number = '"ref":'.$id;

        foreach ([SitePart::query()->pluck('published_blocks'), SiteLayout::query()->pluck('published_blocks')] as $lists) {
            foreach ($lists as $blocks) {
                $json = (string) json_encode($blocks);

                if (str_contains($json, $needle) || preg_match('/'.preg_quote($number, '/').'(?!\d)/', $json) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
