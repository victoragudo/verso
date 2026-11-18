<?php

namespace App\Verso\Editor\CustomCode;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Cache\PageCache;

// Reads and saves custom CSS and JavaScript. CSS is cleaned before it is saved.
class CustomCodeStore
{
    /** @var array<string, CustomCode|null> rows read during this request */
    protected array $loaded = [];

    public function __construct(
        protected StylesheetSanitizer $css,
        protected PageCache $pages,
        protected int $maxJsLength,
    ) {}

    // The code of the whole site (entry null) or of one entry.
    public function find(?int $entryId): ?CustomCode
    {
        $key = $entryId === null ? 'site' : 'entry:'.$entryId;

        if (! array_key_exists($key, $this->loaded)) {
            $this->loaded[$key] = CustomCode::query()
                ->when($entryId === null, fn ($query) => $query->whereNull('entry_id'), fn ($query) => $query->where('entry_id', $entryId))
                ->first();
        }

        return $this->loaded[$key];
    }

    /**
     * Saves the code. A null value keeps what is saved. Empty CSS and JS remove the row.
     */
    public function save(?Entry $entry, ?string $css, ?string $js, ?User $user): ?CustomCode
    {
        $code = $this->find($entry?->id) ?? new CustomCode(['entry_id' => $entry?->id]);

        if ($css !== null) {
            $code->css = $this->css->clean($css) ?: null;
        }

        if ($js !== null) {
            $code->js = trim(mb_substr($js, 0, $this->maxJsLength)) ?: null;
        }

        $code->user_id = $user?->id;
        $this->loaded = [];
        $this->pages->flush();

        if ($code->css === null && $code->js === null) {
            if ($code->exists) {
                $code->delete();
            }

            return null;
        }

        $code->save();

        return $code;
    }
}
