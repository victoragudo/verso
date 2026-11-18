<?php

namespace App\Verso\Seo\AltText;

// A service that writes alt text suggestions, offered by a plugin (like the AI Assistant).
// The endpoint gets POST {"media_id": 12} and answers {"result": {"alt": "..."}}. Suggestions are only a preview.
final readonly class AltTextSuggester
{
    public function __construct(
        public string $label,
        public string $endpoint,
        public bool $ready,
        public ?string $settingsUrl = null,
    ) {}

    /** @return array{label: string, endpoint: string, ready: bool, settingsUrl: string|null} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'endpoint' => $this->endpoint, 'ready' => $this->ready, 'settingsUrl' => $this->settingsUrl];
    }
}
