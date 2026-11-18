<?php

namespace Verso\Plugins\Analytics\Support;

use Illuminate\Support\Str;
use Verso\Plugins\Analytics\Models\VisitorSalt;

// Turns IP, user agent and site into a daily visitor id. The salt changes every day, so ids cannot be linked across days.
class VisitorHasher
{
    /** @var array<string, string> */
    protected array $salts = [];

    public function hash(string $day, string $ip, string $userAgent, string $site): string
    {
        return hash('sha256', $this->saltFor($day)."\n".$ip."\n".$userAgent."\n".strtolower($site));
    }

    // The salt of a day. A new day gets a new random salt and the older salts are deleted.
    public function saltFor(string $day): string
    {
        if (isset($this->salts[$day])) {
            return $this->salts[$day];
        }

        $salt = VisitorSalt::query()->where('day', $day)->value('salt');

        if (! is_string($salt)) {
            VisitorSalt::query()->insertOrIgnore(['day' => $day, 'salt' => Str::random(64)]);
            $this->deleteBefore($day);
            $salt = (string) VisitorSalt::query()->where('day', $day)->value('salt');
        }

        return $this->salts[$day] = $salt;
    }

    // Remove the salts of days before the given day.
    public function deleteBefore(string $day): int
    {
        $this->salts = array_filter($this->salts, fn (string $saltDay): bool => $saltDay >= $day, ARRAY_FILTER_USE_KEY);

        return VisitorSalt::query()->where('day', '<', $day)->delete();
    }
}
