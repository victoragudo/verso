<?php

namespace App\Verso\Updates\Support;

use Illuminate\Support\Str;

// Turns release notes (markdown) into safe HTML: raw HTML is escaped and unsafe links are dropped.
class ReleaseNotes
{
    public static function toHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return trim(Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]));
    }
}
