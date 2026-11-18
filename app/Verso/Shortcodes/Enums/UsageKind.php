<?php

namespace App\Verso\Shortcodes\Enums;

// How an entry embeds a plugin item.
enum UsageKind: string
{
    case Block = 'block';
    case Shortcode = 'shortcode';
    // A style preset used by a block (attrs.preset), so pages that use a changed preset are drawn again.
    case Style = 'style';
}
