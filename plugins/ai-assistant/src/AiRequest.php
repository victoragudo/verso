<?php

namespace Verso\Plugins\AiAssistant;

// One request to the AI provider: instructions, the user content and an optional JSON schema for the answer.
final readonly class AiRequest
{
    /**
     * @param  list<array<string, mixed>>  $content  content blocks of the user message
     * @param  array<string, mixed>|null  $schema  JSON schema the answer must follow
     */
    public function __construct(
        public string $system,
        public array $content,
        public ?array $schema = null,
    ) {}

    // A request with a single text block.
    /**
     * @param  array<string, mixed>|null  $schema
     */
    public static function text(string $system, string $text, ?array $schema = null): self
    {
        return new self($system, [['type' => 'text', 'text' => $text]], $schema);
    }
}
