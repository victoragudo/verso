<?php

namespace App\Verso\System\Config;

use App\Verso\I18n\Support\IcuMessage;
use App\Verso\I18n\Translate;

// The unit a number is stored in. Sizes and times can be shown in bigger units in the admin.
enum Unit: string
{
    case Bytes = 'bytes';
    case Kilobytes = 'kb';
    case Megabytes = 'mb';
    case Milliseconds = 'ms';
    case Seconds = 'seconds';
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';
    case Items = 'items';
    case Characters = 'characters';
    case Pixels = 'pixels';
    case Percent = 'percent';
    case Requests = 'requests';
    case None = 'none';

    // "size" or "time" for units that can be converted, null for the others.
    public function dimension(): ?string
    {
        return match ($this) {
            self::Bytes, self::Kilobytes, self::Megabytes => 'size',
            self::Milliseconds, self::Seconds, self::Minutes, self::Hours, self::Days => 'time',
            default => null,
        };
    }

    // How many of the smallest unit (bytes or milliseconds) one of this unit holds.
    public function factor(): int
    {
        return match ($this) {
            self::Kilobytes => 1024,
            self::Megabytes => 1024 ** 2,
            self::Seconds => 1000,
            self::Minutes => 60_000,
            self::Hours => 3_600_000,
            self::Days => 86_400_000,
            default => 1,
        };
    }

    public function amount(int|float $count): string
    {
        $message = match ($this) {
            self::Bytes => '{count, plural, one {# byte} other {# bytes}}',
            self::Kilobytes => '{count} KB',
            self::Megabytes => '{count} MB',
            self::Milliseconds => '{count} ms',
            self::Seconds => '{count, plural, one {# second} other {# seconds}}',
            self::Minutes => '{count, plural, one {# minute} other {# minutes}}',
            self::Hours => '{count, plural, one {# hour} other {# hours}}',
            self::Days => '{count, plural, one {# day} other {# days}}',
            self::Items => '{count, plural, one {# item} other {# items}}',
            self::Characters => '{count, plural, one {# character} other {# characters}}',
            self::Pixels => '{count, plural, one {# pixel} other {# pixels}}',
            self::Requests => '{count, plural, one {# request} other {# requests}}',
            self::Percent => null,
            self::None => null,
        };

        if ($message !== null) {
            return Translate::icu($message, ['count' => $count]);
        }

        return IcuMessage::format($this === self::Percent ? '{count}%' : '{count}', ['count' => $count], app()->getLocale());
    }

    // Turn a value in this unit into bytes. Only for size units.
    public function toBytes(int|float $value): int
    {
        return (int) round($value * $this->factor());
    }
}
