<?php

namespace App\Verso\Seo\Links;

// What an internal link points to: a live page, a redirect, or nothing.
enum LinkStatus: string
{
    case Ok = 'ok';
    case Redirect = 'redirect';
    case Broken = 'broken';
}
