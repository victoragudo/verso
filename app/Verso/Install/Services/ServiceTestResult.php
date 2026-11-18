<?php

namespace App\Verso\Install\Services;

// The answer of a service test button: did it work, and what to tell the user.
final readonly class ServiceTestResult
{
    public function __construct(
        public bool $ok,
        public string $message,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'message' => $this->message];
    }
}
