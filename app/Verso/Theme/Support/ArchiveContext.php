<?php

namespace App\Verso\Theme\Support;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Theme\Enums\ArchiveKind;

// Tells filters which public list page is being built: home, archive, term or search.
final readonly class ArchiveContext
{
    public function __construct(
        public ArchiveKind $kind,
        public ?ContentType $contentType = null,
        public ?Taxonomy $taxonomy = null,
        public ?Term $term = null,
        public ?string $search = null,
        public ?User $author = null,
    ) {}

    public static function author(User $author, ?ContentType $contentType = null): self
    {
        return new self(ArchiveKind::Author, contentType: $contentType, author: $author);
    }

    public static function home(?ContentType $contentType = null): self
    {
        return new self(ArchiveKind::Home, contentType: $contentType);
    }

    public static function archive(ContentType $contentType): self
    {
        return new self(ArchiveKind::Archive, contentType: $contentType);
    }

    public static function taxonomy(Taxonomy $taxonomy, Term $term): self
    {
        return new self(ArchiveKind::Taxonomy, taxonomy: $taxonomy, term: $term);
    }

    public static function search(string $search): self
    {
        return new self(ArchiveKind::Search, search: $search);
    }

    public function is(ArchiveKind $kind): bool
    {
        return $this->kind === $kind;
    }
}
