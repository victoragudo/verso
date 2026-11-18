<?php

namespace App\Verso\Comments\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;

// Says if comments are on for the site and open on a given entry.
class CommentAvailability
{
    public function __construct(
        protected Settings $settings,
        protected bool $openByDefault,
    ) {}

    // The site wide switch (setting comments.enabled).
    public function enabled(): bool
    {
        return (bool) $this->settings->get('comments.enabled', true);
    }

    // The entry meta comments_open, or the default from config.
    public function entryAllows(Entry $entry): bool
    {
        $meta = $entry->meta ?? [];

        return array_key_exists('comments_open', $meta) ? (bool) $meta['comments_open'] : $this->openByDefault;
    }

    // True when visitors can send a new comment on this entry.
    public function isOpenFor(Entry $entry): bool
    {
        return $this->enabled() && ! $entry->trashed() && $entry->isPublished() && $this->entryAllows($entry);
    }
}
