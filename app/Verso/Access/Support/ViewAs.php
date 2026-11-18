<?php

namespace App\Verso\Access\Support;

// The role an administrator is previewing the admin as, for the current request only.
class ViewAs
{
    // The session key that keeps the previewed role between requests.
    public const SESSION_KEY = 'verso.view_as_role';

    protected ?int $userId = null;

    protected ?string $role = null;

    public function start(int $userId, string $role): void
    {
        $this->userId = $userId;
        $this->role = $role;
    }

    public function stop(): void
    {
        $this->userId = null;
        $this->role = null;
    }

    // The previewed role when this user is the one previewing, or null.
    public function roleFor(int $userId): ?string
    {
        return $this->userId === $userId ? $this->role : null;
    }

    public function active(): bool
    {
        return $this->role !== null;
    }

    public function role(): ?string
    {
        return $this->role;
    }
}
