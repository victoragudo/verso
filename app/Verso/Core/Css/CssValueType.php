<?php

namespace App\Verso\Core\Css;

// What a CSS value measures. It picks the units a value may use.
enum CssValueType: string
{
    case Length = 'length';
    case Angle = 'angle';
    case Time = 'time';
    case Number = 'number';
}
