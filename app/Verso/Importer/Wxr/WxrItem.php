<?php

namespace App\Verso\Importer\Wxr;

// A post, page, attachment or other item from the export file.
final readonly class WxrItem
{
    /**
     * @param  list<WxrTerm>  $terms
     * @param  array<string, string>  $meta
     * @param  list<WxrComment>  $comments
     */
    public function __construct(
        public int $id,
        public string $postType,
        public string $title,
        public string $slug,
        public string $status,
        public string $link,
        public string $content,
        public string $excerpt,
        public string $creator,
        public string $date,
        public string $dateGmt,
        public int $parentId = 0,
        public int $menuOrder = 0,
        public string $attachmentUrl = '',
        public array $terms = [],
        public array $meta = [],
        public array $comments = [],
    ) {}

    public function isAttachment(): bool
    {
        return $this->postType === 'attachment';
    }

    public function metaValue(string $key): ?string
    {
        return $this->meta[$key] ?? null;
    }
}
