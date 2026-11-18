<?php

namespace App\Verso\Install\Plan;

// The first admin account.
final readonly class AdminAccount
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['admin_name'] ?? '')),
            email: strtolower(trim((string) ($data['admin_email'] ?? ''))),
            password: (string) ($data['admin_password'] ?? ''),
        );
    }
}
