<?php

namespace App\Verso\Editor\Support;

// A video found in an embed URL, with the privacy friendly player URL to show it.
final readonly class Embed
{
    public function __construct(
        public string $provider,
        public string $id,
        public string $src,
    ) {}
}
