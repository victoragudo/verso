<?php

namespace Verso\Plugins\AiAssistant\Exceptions;

// The user made too many requests in the current window.
class AiRateLimited extends AiException
{
    public function __construct(public readonly int $retryAfter)
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));

        parent::__construct(__('ai-assistant::You have used all your AI requests for now. Try again in :minutes min.', ['minutes' => $minutes]));
    }

    public function status(): int
    {
        return 429;
    }
}
