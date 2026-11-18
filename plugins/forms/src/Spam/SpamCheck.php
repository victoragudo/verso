<?php

namespace Verso\Plugins\Forms\Spam;

// The verdict of the spam checks and the rule that decided it.
final readonly class SpamCheck
{
    public function __construct(
        public SpamVerdict $verdict,
        public ?string $reason = null,
    ) {}

    public static function clean(): self
    {
        return new self(SpamVerdict::Clean);
    }

    public function isClean(): bool
    {
        return $this->verdict === SpamVerdict::Clean;
    }
}
