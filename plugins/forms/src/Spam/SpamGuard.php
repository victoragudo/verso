<?php

namespace Verso\Plugins\Forms\Spam;

use App\Verso\Comments\Support\FormTimer;
use Illuminate\Http\Request;
use Verso\Plugins\Forms\Support\FormsSettings;

// Spam checks that run on the site, with no third party: honeypot, time trap, links and blocked words.
class SpamGuard
{
    public function __construct(
        protected FormsSettings $settings,
    ) {}

    public static function honeypotField(): string
    {
        return (string) config('forms.spam.honeypot_field');
    }

    // The time trap token for a form shown now.
    public function timerToken(): string
    {
        return $this->timer()->token();
    }

    /**
     * @param  array<string, mixed>  $values  the cleaned values of the form
     */
    public function check(Request $request, array $values): SpamCheck
    {
        if (trim((string) $request->input(self::honeypotField(), '')) !== '') {
            return new SpamCheck(SpamVerdict::Bot, 'honeypot');
        }

        $token = $request->input(FormTimer::FIELD);
        $timer = $this->timer();

        if (! is_string($token) || $timer->elapsed($token) === null) {
            return new SpamCheck(SpamVerdict::Bot, 'missing_timer');
        }

        if ($timer->isTooFast($token)) {
            return new SpamCheck(SpamVerdict::Bot, 'too_fast');
        }

        $text = mb_strtolower($this->flatten($values));
        $maxLinks = $this->settings->maxLinks();

        if ($maxLinks > 0 && $this->countLinks($text) >= $maxLinks) {
            return new SpamCheck(SpamVerdict::Spam, 'links');
        }

        foreach ($this->settings->blockedWords() as $word) {
            if (str_contains($text, $word)) {
                return new SpamCheck(SpamVerdict::Spam, 'blocked_word');
            }
        }

        return SpamCheck::clean();
    }

    public function countLinks(string $text): int
    {
        return (int) preg_match_all('~(?:https?://|www\.)\S+~i', $text);
    }

    protected function timer(): FormTimer
    {
        // Old tokens are fine: cached pages keep the token they were rendered with.
        return new FormTimer($this->settings->minSeconds(), PHP_INT_MAX);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function flatten(array $values): string
    {
        return implode("\n", array_map(
            fn ($value): string => is_array($value) ? implode(' ', array_map('strval', $value)) : (is_scalar($value) ? (string) $value : ''),
            $values,
        ));
    }
}
