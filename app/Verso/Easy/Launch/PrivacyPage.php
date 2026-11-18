<?php

namespace App\Verso\Easy\Launch;

use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Support\EasyState;

// Finds the privacy page of the site: the one made with "Create a privacy page", or a page with the privacy slug.
class PrivacyPage
{
    public function __construct(protected EasyState $state) {}

    // The published privacy page, or null.
    public function find(): ?Entry
    {
        $id = $this->state->privacyPageId();
        $query = Entry::query()->ofType('page')->published();

        $page = $id === null ? null : (clone $query)->whereKey($id)->first();

        return $page ?? $query->where('slug', $this->slug())->whereNull('parent_id')->first();
    }

    // Any privacy page, even a draft, so a second click never makes a copy.
    public function findAny(): ?Entry
    {
        $id = $this->state->privacyPageId();
        $page = $id === null ? null : Entry::query()->ofType('page')->whereKey($id)->first();

        return $page ?? Entry::query()->ofType('page')->where('slug', $this->slug())->whereNull('parent_id')->first();
    }

    public function slug(): string
    {
        return (string) config('verso.easy.privacy.slug');
    }
}
