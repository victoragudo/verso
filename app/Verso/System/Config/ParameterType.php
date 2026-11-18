<?php

namespace App\Verso\System\Config;

// The kind of value a system setting holds.
enum ParameterType: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Text = 'text';
    case Choice = 'choice';
    case TextList = 'list';
}
