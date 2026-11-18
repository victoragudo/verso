<?php

namespace Verso\Plugins\AiAssistant\Exceptions;

// The provider failed, refused or sent an answer we cannot use.
class AiProviderError extends AiException
{
    public function __construct(string $message, protected int $httpStatus = 502)
    {
        parent::__construct($message);
    }

    public static function rejectedKey(): self
    {
        return new self(__('ai-assistant::The provider rejected the API key. Check it on the AI assistant settings page.'));
    }

    public static function busy(): self
    {
        return new self(__('ai-assistant::The AI provider is busy right now. Try again in a moment.'));
    }

    public static function unreachable(): self
    {
        return new self(__('ai-assistant::Verso could not reach the AI provider. Try again in a moment.'));
    }

    public static function timeout(): self
    {
        return new self(__('ai-assistant::The AI provider took too long to answer. Try again with a shorter text.'), 504);
    }

    public static function invalidRequest(string $detail): self
    {
        return new self(__('ai-assistant::The AI provider did not accept the request: :detail', ['detail' => $detail]), 422);
    }

    public static function unsupportedImage(): self
    {
        return new self(__('ai-assistant::The AI provider did not accept the request: only JPEG, PNG, GIF and WebP images can be described.'), 422);
    }

    public static function missingImage(): self
    {
        return new self(__('ai-assistant::The AI provider did not accept the request: the image file is missing.'), 422);
    }

    public static function lostSegments(): self
    {
        return new self(__('ai-assistant::The translation lost some text pieces. Try again.'));
    }

    public static function refused(): self
    {
        return new self(__('ai-assistant::The model declined this request. Try different wording.'), 422);
    }

    public static function cutOff(): self
    {
        return new self(__('ai-assistant::The answer was cut short. Raise the max tokens setting or send less text.'));
    }

    public static function badAnswer(): self
    {
        return new self(__('ai-assistant::The AI provider sent an answer Verso could not read. Try again.'));
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
