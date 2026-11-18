<?php

namespace App\Verso\Site\Parts;

// Draft: saved changes visitors do not see yet. Published: visitors see the saved blocks.
enum SitePartStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
