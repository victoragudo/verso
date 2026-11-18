<?php

namespace App\Verso\Importer\WordPress;

// Builds the stored path for an old WordPress query URL, like "/?p=123".
final class LegacyQueryPath
{
    // Query keys WordPress uses to find a post by id.
    public const KEYS = ['p', 'page_id'];

    public static function make(string $key, int $id): string
    {
        return '/?'.$key.'='.$id;
    }
}
