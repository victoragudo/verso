<?php

namespace Verso\Plugins\AiAssistant\Contracts;

use Verso\Plugins\AiAssistant\AiRequest;
use Verso\Plugins\AiAssistant\AiResponse;
use Verso\Plugins\AiAssistant\Exceptions\AiException;

// A service that turns a prompt into an answer, like the Anthropic Messages API.
interface AiProvider
{
    // A short name shown in the admin, like "Anthropic".
    public function name(): string;

    /**
     * Send one request and wait for the full answer.
     *
     * @throws AiException
     */
    public function send(AiRequest $request): AiResponse;
}
