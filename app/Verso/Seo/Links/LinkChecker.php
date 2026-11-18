<?php

namespace App\Verso\Seo\Links;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Filters\EntryContentFilter;
use App\Verso\Seo\Models\PageReport;
use App\Verso\Seo\Models\SeoLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

// Checks one published entry: builds its content, times it, and stores its internal links with their status.
class LinkChecker
{
    public function __construct(
        protected ContentRenderer $content,
        protected BlockRenderer $blocks,
        protected LinkExtractor $extractor,
        protected LinkTargetResolver $targets,
    ) {}

    public function check(Entry $entry): PageReport
    {
        $started = hrtime(true);

        try {
            $html = $this->render($entry);
        } catch (Throwable) {
            $html = '';
        }

        $renderMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $found = $this->extractor->extract($html);
        $rows = [];
        $broken = 0;

        foreach ($found->internal as $path) {
            $target = $this->targets->resolve($path);
            $broken += (int) ($target->status === LinkStatus::Broken);
            $rows[] = [
                'source_entry_id' => $entry->id,
                'path' => mb_substr($path, 0, 255),
                'target_entry_id' => $target->entryId === $entry->id ? null : $target->entryId,
                'status' => $target->status->value,
            ];
        }

        return DB::transaction(function () use ($entry, $rows, $renderMs, $found, $broken): PageReport {
            SeoLink::query()->where('source_entry_id', $entry->id)->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                SeoLink::query()->insert($chunk);
            }

            return PageReport::query()->updateOrCreate(['entry_id' => $entry->id], [
                'render_ms' => $renderMs,
                'internal_links' => count($found->internal),
                'external_links' => $found->external,
                'broken_links' => $broken,
                'checked_at' => Carbon::now(),
            ]);
        });
    }

    // The entry content as visitors get it, built without the block cache so the time is real.
    protected function render(Entry $entry): string
    {
        $blocks = is_array($entry->blocks) ? $entry->blocks : [];
        $html = $this->blocks->withStyles(function () use ($blocks, $entry): string {
            $html = '';

            foreach ($blocks as $block) {
                $html .= $this->content->renderBlock($block, $entry);
            }

            return $html;
        });

        return EntryContentFilter::apply($html, $entry);
    }
}
