<?php

namespace App\Verso\Theme\Templates;

// The kind of public page a list of template candidates is built for.
enum TemplateContext: string
{
    case Entry = 'entry';
    case FrontPage = 'front-page';
    case Taxonomy = 'taxonomy';
    case Archive = 'archive';
    case Author = 'author';
    case Home = 'home';
    case Search = 'search';
    case NotFound = '404';
    case Part = 'part';
    case Custom = 'custom';
}
