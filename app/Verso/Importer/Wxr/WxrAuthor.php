<?php

namespace App\Verso\Importer\Wxr;

// An author from the export file.
final readonly class WxrAuthor
{
    public function __construct(
        public int $id,
        public string $login,
        public string $email,
        public string $displayName,
        public string $firstName = '',
        public string $lastName = '',
    ) {}

    // The best name to show: display name, then first and last name, then login.
    public function name(): string
    {
        $full = trim($this->firstName.' '.$this->lastName);

        return $this->displayName !== '' ? $this->displayName : ($full !== '' ? $full : $this->login);
    }
}
