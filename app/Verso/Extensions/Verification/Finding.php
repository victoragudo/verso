<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\I18n\Translate;

// One problem found while checking a plugin: which rule, how serious, and where.
final readonly class Finding
{
    public string $message;

    public string $text;

    /** @var array<string, string> */
    public array $replace;

    /**
     * @param  array<string, string|int>  $replace
     */
    public function __construct(
        public string $rule,
        public Severity $severity,
        string $message,
        public ?string $file = null,
        public ?int $line = null,
        array $replace = [],
    ) {
        $this->text = $message;
        $this->replace = array_map(strval(...), $replace);
        $this->message = self::fill($message, $this->replace);
    }

    /**
     * Rebuild a finding from stored report data.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $replace = is_array($data['replace'] ?? null) ? $data['replace'] : [];
        $hasText = isset($data['text']) && is_string($data['text']);

        return new self(
            rule: (string) ($data['rule'] ?? 'unknown'),
            severity: Severity::tryFrom((string) ($data['severity'] ?? '')) ?? Severity::Info,
            message: $hasText ? $data['text'] : (string) ($data['message'] ?? ''),
            file: isset($data['file']) ? (string) $data['file'] : null,
            line: isset($data['line']) ? (int) $data['line'] : null,
            replace: $hasText ? array_map(strval(...), $replace) : [],
        );
    }

    public function translated(): string
    {
        return Translate::text($this->text, $this->replace);
    }

    /**
     * @return array{rule: string, severity: string, message: string, file: string|null, line: int|null}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }

    /**
     * @return array{rule: string, severity: string, message: string, file: string|null, line: int|null, text?: string, replace?: array<string, string>}
     */
    public function toStoredArray(): array
    {
        if ($this->replace === []) {
            return $this->toArray();
        }

        return [...$this->toArray(), 'text' => $this->text, 'replace' => $this->replace];
    }

    /**
     * @return array{rule: string, severity: string, message: string, file: string|null, line: int|null}
     */
    public function toDisplayArray(): array
    {
        return [...$this->toArray(), 'message' => $this->translated()];
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected static function fill(string $text, array $replace): string
    {
        $pairs = [];

        foreach ($replace as $key => $value) {
            $pairs[':'.$key] = $value;
        }

        return strtr($text, $pairs);
    }
}
