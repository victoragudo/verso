<?php

namespace App\Verso\Install;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Install\Pipeline\InstallProgress;
use Illuminate\Filesystem\Filesystem;
use Throwable;

// Knows if Verso is installed: the lock file exists, or an admin exists and no install is running.
class InstallState
{
    // Setting saved when the install finished.
    public const SETTING = 'install.completed_at';

    public function __construct(
        protected Filesystem $files,
        protected string $lockFile,
        protected ?InstallProgress $progress = null,
    ) {}

    public function isInstalled(): bool
    {
        if ($this->isLocked()) {
            return true;
        }

        return ! $this->inProgress() && $this->adminExists();
    }

    public function isLocked(): bool
    {
        return $this->files->exists($this->lockFile);
    }

    // True while the wizard runs the install steps.
    public function inProgress(): bool
    {
        return $this->progress?->exists() ?? false;
    }

    // False when the database is not ready yet.
    public function adminExists(): bool
    {
        try {
            return User::query()->where('role', Role::Admin)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    // Write the lock file so the web installer stays closed.
    public function lock(): void
    {
        $this->files->ensureDirectoryExists(dirname($this->lockFile));
        $this->files->put($this->lockFile, 'Installed at '.now()->toIso8601String().PHP_EOL);
    }

    public function lockFile(): string
    {
        return $this->lockFile;
    }
}
