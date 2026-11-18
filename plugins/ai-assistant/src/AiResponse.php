<?php

namespace Verso\Plugins\AiAssistant;

use JsonException;
use Verso\Plugins\AiAssistant\Exceptions\AiProviderError;

// The answer of the AI provider with the tokens it used.
final readonly class AiResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public string $model = '',
        public string $stopReason = 'end_turn',
    ) {}

    // The answer read as a JSON object, for requests with a schema.
    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        try {
            $data = json_decode($this->text, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw AiProviderError::badAnswer();
        }

        if (! is_array($data)) {
            throw AiProviderError::badAnswer();
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
