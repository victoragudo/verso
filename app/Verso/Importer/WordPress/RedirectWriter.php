<?php

namespace App\Verso\Importer\WordPress;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Models\Redirect;
use App\Verso\Importer\Support\ImportReport;

// Adds 301 redirects from old WordPress URLs to the new Verso URLs.
class RedirectWriter
{
    protected const STATUS = 301;

    public function __construct(protected bool $dateRedirects = true) {}

    public function write(ImportedEntry $imported, ImportState $state): void
    {
        $entry = Entry::query()->find($imported->entryId);

        if ($entry === null || ! $entry->isPublished()) {
            return;
        }

        $target = $entry->path();

        foreach ($this->oldPaths($imported) as $from) {
            $this->point($from, $target, $state);
        }

        foreach ($this->queryKeys($imported) as $key) {
            $this->pointQuery($key, $imported->wordpressId, $target, $state);
        }
    }

    // The old path from the permalink, plus date based paths for posts.
    /** @return list<string> */
    public function oldPaths(ImportedEntry $imported): array
    {
        $paths = [];
        $linkPath = (string) parse_url($imported->link, PHP_URL_PATH);
        $hasQuery = parse_url($imported->link, PHP_URL_QUERY) !== null;

        if (! $hasQuery && trim($linkPath, '/') !== '') {
            $paths[] = Redirect::normalizePath($linkPath);
        }

        if ($this->dateRedirects && $imported->postType === 'post' && $imported->slug !== '' && $imported->localDate !== null) {
            $paths[] = '/'.$imported->localDate->format('Y/m/d').'/'.$imported->slug;
            $paths[] = '/'.$imported->localDate->format('Y/m').'/'.$imported->slug;
        }

        return array_values(array_unique($paths));
    }

    // WordPress answers "?p=ID" for every type and "?page_id=ID" for pages.
    /** @return list<string> */
    public function queryKeys(ImportedEntry $imported): array
    {
        return $imported->postType === 'page' ? ['page_id', 'p'] : ['p'];
    }

    protected function point(string $from, string $to, ImportState $state): void
    {
        if ($from === $to) {
            return;
        }

        $existing = Redirect::query()->where('from_path', $from)->first();

        if ($existing !== null && $existing->to_path === $to) {
            $state->report->add('redirects', ImportReport::SKIPPED);

            return;
        }

        Redirect::point($from, $to, self::STATUS);
        $state->report->add('redirects', $existing === null ? ImportReport::CREATED : ImportReport::UPDATED);
    }

    // Query string redirects are stored as "/?p=123" and served by LegacyQueryRedirects.
    protected function pointQuery(string $key, int $wordpressId, string $to, ImportState $state): void
    {
        $from = LegacyQueryPath::make($key, $wordpressId);
        $existing = Redirect::query()->where('from_path', $from)->first();

        if ($existing !== null && $existing->to_path === $to) {
            $state->report->add('redirects', ImportReport::SKIPPED);

            return;
        }

        Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => self::STATUS]);
        $state->report->add('redirects', $existing === null ? ImportReport::CREATED : ImportReport::UPDATED);
    }
}
