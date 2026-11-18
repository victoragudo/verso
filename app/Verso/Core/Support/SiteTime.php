<?php

namespace App\Verso\Core\Support;

use App\Verso\Core\Settings\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

// Converts dates between UTC (how they are stored) and the site time zone (how people see them).
class SiteTime
{
    // A date and time with an explicit offset or "Z", like 2026-11-20T09:30:00+01:00.
    protected const OFFSET_PATTERN = '/(Z|[+-]\d{2}:?\d{2})$/i';

    public function __construct(protected Settings $settings) {}

    // The site.timezone setting, or the app time zone when the setting is not a valid zone.
    public function timezone(): string
    {
        $zone = $this->settings->get('site.timezone');

        if (is_string($zone) && in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return $zone;
        }

        return (string) config('app.timezone', 'UTC');
    }

    // The same moment, shown in the site time zone.
    public function local(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone($this->timezone());
    }

    // An ISO 8601 date with the site offset, like 2026-11-20T10:30:00+01:00.
    public function iso(?CarbonInterface $date): ?string
    {
        return $date === null ? null : $this->local($date)->toAtomString();
    }

    // A date in the site time zone with a PHP date format, like "F j, Y".
    public function format(CarbonInterface $date, string $format): string
    {
        return $this->local($date)->format($format);
    }

    // Reads a date typed in the site time zone and returns it in UTC.
    // A value with its own offset (or "Z") keeps that offset.
    public function toUtc(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        $value = trim($value);
        $zone = preg_match(self::OFFSET_PATTERN, $value) === 1 ? null : $this->timezone();

        return CarbonImmutable::parse($value, $zone)->utc();
    }
}
