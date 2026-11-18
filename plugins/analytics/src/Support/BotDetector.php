<?php

namespace Verso\Plugins\Analytics\Support;

// Spots crawlers, monitors and scripts by words in their user agent.
class BotDetector
{
    /**
     * @param  list<string>  $words
     */
    public function __construct(protected array $words) {}

    // An empty user agent is treated as a bot too.
    public function isBot(?string $userAgent): bool
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach ($this->words as $word) {
            $word = strtolower(trim($word));

            if ($word !== '' && str_contains($userAgent, $word)) {
                return true;
            }
        }

        return false;
    }
}
