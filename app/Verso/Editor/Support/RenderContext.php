<?php

namespace App\Verso\Editor\Support;

use App\Verso\Content\Models\Entry;
use Closure;

// What the page being drawn is about: the current entry (also inside a query loop) and the kind of page.
// Dynamic blocks like "Post title" or "Query loop" read it. One per request.
class RenderContext
{
    /** @var list<Entry> entries being drawn, the innermost last */
    protected array $entries = [];

    // The kind of public page, like "entry", "archive", "taxonomy", "search" or "404".
    protected ?string $page = null;

    // The thing the page shows: an entry, a term, a content type or an author.
    protected ?object $subject = null;

    /** @var array<string, mixed> page data, like the list of entries of an archive */
    protected array $data = [];

    // The entry being drawn, or null outside of one.
    public function entry(): ?Entry
    {
        return $this->entries === [] ? null : $this->entries[array_key_last($this->entries)];
    }

    /**
     * Runs a render with an entry as the current one.
     *
     * @template T
     *
     * @param  Closure(): T  $render
     * @return T
     */
    public function withEntry(Entry $entry, Closure $render): mixed
    {
        $this->entries[] = $entry;

        try {
            return $render();
        } finally {
            array_pop($this->entries);
        }
    }

    /** @param array<string, mixed> $data */
    public function setPage(string $page, ?object $subject = null, array $data = []): void
    {
        $this->page = $page;
        $this->subject = $subject;
        $this->data = $data;
    }

    public function page(): ?string
    {
        return $this->page;
    }

    public function subject(): ?object
    {
        return $this->subject;
    }

    public function data(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    // Forgets the page, for the next request of a long running process.
    public function reset(): void
    {
        $this->entries = [];
        $this->page = null;
        $this->subject = null;
        $this->data = [];
    }
}
