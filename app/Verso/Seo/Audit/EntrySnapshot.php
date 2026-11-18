<?php

namespace App\Verso\Seo\Audit;

// The SEO facts of one published entry, read once and shared by the checks.
final readonly class EntrySnapshot
{
    public function __construct(
        public int $id,
        public string $type,
        public string $typeLabel,
        public string $title,
        public string $seoTitle,
        public ?string $description,
        public bool $hasImage,
        public bool $noindex,
        public bool $isArticle,
        public bool $isFrontPage,
        public int $imagesWithoutAlt,
        public string $editUrl,
        public ?string $rating = null,
    ) {}

    // Search engines may show this entry, so its title and description matter.
    public function isIndexable(): bool
    {
        return ! $this->noindex;
    }

    /**
     * @param  array<string, string|int|float>  $values
     */
    public function item(string $message, array $values = []): IssueItem
    {
        return new IssueItem(
            key: 'entry-'.$this->id,
            title: $this->title,
            detail: $message,
            editUrl: $this->editUrl,
            type: $this->type,
            typeLabel: $this->typeLabel,
            detailValues: $values,
        );
    }
}
