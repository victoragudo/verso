<?php

namespace App\Verso\Comments\Support;

// A simple spam check: comments with many links are likely spam.
class SpamScore
{
    public function __construct(protected int $spamLinks) {}

    // Number of links in the text (http, https and www addresses).
    public function score(string ...$texts): int
    {
        $score = 0;

        foreach ($texts as $text) {
            $score += preg_match_all('~(?:https?://|www\.)\S+~i', $text);
        }

        return $score;
    }

    public function isSpam(string ...$texts): bool
    {
        return $this->spamLinks > 0 && $this->score(...$texts) >= $this->spamLinks;
    }
}
