<?php

namespace App\Verso\Theme\Enums;

// The kind of public list page that shows entries.
enum ArchiveKind: string
{
    case Home = 'home';
    case Archive = 'archive';
    case Taxonomy = 'taxonomy';
    case Author = 'author';
    case Search = 'search';
}
