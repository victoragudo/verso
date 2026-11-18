<?php

namespace App\Verso\Importer\Wxr;

// A comment on a post from the export file.
final readonly class WxrComment
{
    public function __construct(
        public int $id,
        public string $authorName,
        public string $authorEmail,
        public string $authorUrl,
        public string $authorIp,
        public string $date,
        public string $dateGmt,
        public string $content,
        public string $approved,
        public string $type,
        public int $parentId,
        public int $userId,
    ) {}

    // Approved ("1") and waiting ("0") comments are kept. Spam and trash are not.
    public function isWanted(): bool
    {
        return in_array($this->approved, ['0', '1'], true)
            && in_array($this->type, ['', 'comment'], true);
    }

    public function isApproved(): bool
    {
        return $this->approved === '1';
    }
}
