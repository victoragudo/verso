<?php

namespace App\Verso\Layouts\Conditions;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Editor\Support\RenderContext;

// The page being drawn, as the layout conditions see it.
final readonly class PageContext
{
    public function __construct(
        // "entry", "front-page", "archive", "taxonomy", "author", "home", "search", "404" or "custom".
        public string $kind,
        public ?Entry $entry = null,
        public ?Term $term = null,
        public ?ContentType $contentType = null,
        public ?User $author = null,
        // The person looking at the page, null for visitors who are not signed in.
        public ?User $viewer = null,
    ) {}

    public static function fromRender(RenderContext $render, ?User $viewer): self
    {
        $subject = $render->subject();
        $entry = $subject instanceof Entry ? $subject : null;

        return new self(
            kind: $render->page() ?? 'custom',
            entry: $entry,
            term: $subject instanceof Term ? $subject : null,
            contentType: $subject instanceof ContentType ? $subject : null,
            author: $subject instanceof User ? $subject : $entry?->author,
            viewer: $viewer,
        );
    }

    // The content type of the page: of the entry, or of the archive.
    public function type(): ?string
    {
        return $this->entry->type ?? $this->contentType?->name;
    }
}
