<?php

namespace App\Verso\Site\Parts;

use App\Verso\Site\Events\SitePartPublished;
use App\Verso\Site\Models\SitePart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Saves, publishes and restores edited theme parts. Theme files are never written.
class SitePartStore
{
    /** @var array<string, array<string, array<int, mixed>>> published blocks per theme and part, loaded once per request */
    protected array $published = [];

    /** @var array<string, array<string, array<int, mixed>>> blocks shown instead of the published ones, for the editor preview */
    protected array $previews = [];

    /** @var array<string, true> themes whose working copies are shown, for the editor preview */
    protected array $drafts = [];

    public function __construct(protected int $maxRevisions) {}

    // Shows the saved drafts of a theme instead of the published blocks, in this request only.
    public function useDrafts(string $theme): void
    {
        $this->drafts[$theme] = true;
        unset($this->published[$theme]);
    }

    /**
     * Edited parts of a theme by key.
     *
     * @return array<string, SitePart>
     */
    public function forTheme(string $theme): array
    {
        return SitePart::query()->where('theme', $theme)->withCount('revisions')->get()->keyBy('part')->all();
    }

    public function find(string $theme, string $part): ?SitePart
    {
        return SitePart::query()->where('theme', $theme)->where('part', $part)->first();
    }

    /**
     * Saves the working copy. Visitors still see the published blocks or the theme file.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function saveDraft(string $theme, string $part, array $blocks, ?int $userId = null): SitePart
    {
        $sitePart = $this->find($theme, $part) ?? new SitePart(['theme' => $theme, 'part' => $part]);
        // Loose compare: MySQL gives JSON keys back in another order, so a strict compare sees a change that is not there.
        $sitePart->fill([
            'blocks' => $blocks,
            'status' => $sitePart->isLive() && $sitePart->published_blocks == $blocks ? SitePartStatus::Published : SitePartStatus::Draft,
            'user_id' => $userId,
        ])->save();

        return $sitePart;
    }

    /**
     * Makes the blocks live, keeps a revision and empties the page cache.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function publish(string $theme, string $part, array $blocks, ?int $userId = null): SitePart
    {
        $sitePart = DB::transaction(function () use ($theme, $part, $blocks, $userId): SitePart {
            $sitePart = $this->find($theme, $part) ?? new SitePart(['theme' => $theme, 'part' => $part]);
            $sitePart->fill([
                'blocks' => $blocks,
                'published_blocks' => $blocks,
                'status' => SitePartStatus::Published,
                'user_id' => $userId,
                'published_at' => Carbon::now(),
            ])->save();

            $sitePart->revisions()->create(['blocks' => $blocks, 'user_id' => $userId]);
            $this->pruneRevisions($sitePart);

            return $sitePart;
        });

        $this->changed($theme, $part);

        return $sitePart;
    }

    // Removes the edited copy and its revisions, so the theme file is used again.
    public function restoreDefault(string $theme, string $part): void
    {
        $deleted = SitePart::query()->where('theme', $theme)->where('part', $part)->delete();

        if ($deleted > 0) {
            $this->changed($theme, $part);
        }
    }

    /**
     * The blocks visitors see for a part, or null when the theme file is used.
     *
     * @return array<int, mixed>|null
     */
    public function published(string $theme, string $part): ?array
    {
        if (isset($this->previews[$theme][$part])) {
            return $this->previews[$theme][$part];
        }

        $this->published[$theme] ??= $this->loadPublished($theme);

        return $this->published[$theme][$part] ?? null;
    }

    // True when a published part of the theme (or a preview) holds a block of this type, at any depth.
    public function uses(string $theme, string $type): bool
    {
        $this->published[$theme] ??= $this->loadPublished($theme);

        foreach ([...$this->published[$theme], ...$this->previews[$theme] ?? []] as $blocks) {
            if ($this->holds($blocks, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $blocks
     */
    protected function holds(array $blocks, string $type): bool
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === $type || (is_array($block['children'] ?? null) && $this->holds($block['children'], $type))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shows these blocks for a part in this request only, for the editor preview.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function preview(string $theme, string $part, array $blocks): void
    {
        $this->previews[$theme][$part] = $blocks;
    }

    /**
     * Every published part of a theme in one query.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function loadPublished(string $theme): array
    {
        $parts = [];

        if (isset($this->drafts[$theme])) {
            foreach (SitePart::query()->where('theme', $theme)->get(['part', 'blocks']) as $row) {
                $parts[$row->part] = $row->blocks;
            }

            return $parts;
        }

        foreach (SitePart::query()->where('theme', $theme)->whereNotNull('published_blocks')->get(['part', 'published_blocks']) as $row) {
            $parts[$row->part] = $row->published_blocks ?? [];
        }

        return $parts;
    }

    // Keeps only the newest revisions.
    protected function pruneRevisions(SitePart $sitePart): void
    {
        $keep = $sitePart->revisions()->limit(max(1, $this->maxRevisions))->pluck('id');

        $sitePart->revisions()->whereNotIn('id', $keep)->delete();
    }

    protected function changed(string $theme, string $part): void
    {
        unset($this->published[$theme]);

        SitePartPublished::dispatch($theme, $part);
    }
}
