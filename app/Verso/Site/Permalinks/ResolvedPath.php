<?php

namespace App\Verso\Site\Permalinks;

use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;

// What a public path points to: an archive, a term, an entry, or a redirect to the right path.
final readonly class ResolvedPath
{
    public const ARCHIVE = 'archive';

    public const TERM = 'term';

    public const ENTRY = 'entry';

    public const REDIRECT = 'redirect';

    protected function __construct(
        public string $kind,
        public ?ContentType $type = null,
        public ?Taxonomy $taxonomy = null,
        public ?Term $term = null,
        public ?Entry $entry = null,
        public ?string $redirectTo = null,
    ) {}

    public static function archive(ContentType $type): self
    {
        return new self(self::ARCHIVE, type: $type);
    }

    public static function term(Taxonomy $taxonomy, Term $term): self
    {
        return new self(self::TERM, taxonomy: $taxonomy, term: $term);
    }

    public static function entry(Entry $entry): self
    {
        return new self(self::ENTRY, entry: $entry);
    }

    public static function redirect(string $path): self
    {
        return new self(self::REDIRECT, redirectTo: $path);
    }

    public function is(string $kind): bool
    {
        return $this->kind === $kind;
    }
}
