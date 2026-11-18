<?php

namespace App\Verso\Importer\Support;

use Carbon\CarbonImmutable;
use Throwable;

// Reads WordPress dates. GMT dates win; local dates use the site timezone.
class WordPressDates
{
    protected const EMPTY_DATE = '0000-00-00 00:00:00';

    public function __construct(protected string $timezone = 'UTC') {}

    public function parse(string $gmt, string $local = ''): ?CarbonImmutable
    {
        return $this->make($gmt, 'UTC') ?? $this->make($local, $this->timezone);
    }

    protected function make(string $value, string $timezone): ?CarbonImmutable
    {
        $value = trim($value);

        if ($value === '' || $value === self::EMPTY_DATE) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
