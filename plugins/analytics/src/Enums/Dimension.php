<?php

namespace Verso\Plugins\Analytics\Enums;

// What a row of the daily table counts: the whole day, one page, one referrer or one device.
enum Dimension: string
{
    case Total = 'total';
    case Page = 'page';
    case Referrer = 'referrer';
    case Device = 'device';
}
