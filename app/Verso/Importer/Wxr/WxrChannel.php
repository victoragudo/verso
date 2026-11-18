<?php

namespace App\Verso\Importer\Wxr;

// Site wide facts from the top of the export file.
final readonly class WxrChannel
{
    public function __construct(
        public string $title = '',
        public string $link = '',
        public string $baseSiteUrl = '',
        public string $baseBlogUrl = '',
        public string $wxrVersion = '',
    ) {}
}
