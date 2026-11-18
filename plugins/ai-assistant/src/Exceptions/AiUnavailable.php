<?php

namespace Verso\Plugins\AiAssistant\Exceptions;

// The assistant cannot run: the plugin is off or no API key is saved.
class AiUnavailable extends AiException
{
    public static function disabled(): self
    {
        return new self(__('ai-assistant::The AI assistant plugin is not enabled.'));
    }

    public static function missingKey(): self
    {
        return new self(__('ai-assistant::Add an API key on the AI assistant settings page first.'));
    }

    public function status(): int
    {
        return 503;
    }
}
