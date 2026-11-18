<?php

namespace App\Verso\Updates\Updater;

use Illuminate\Filesystem\Filesystem;

// A file lock so two updates never run at the same time. The system drops it when the process ends.
class UpdateLock
{
    /** @var resource|null */
    protected $handle = null;

    public function __construct(protected Filesystem $files, protected string $path) {}

    public function acquire(): bool
    {
        if ($this->handle !== null) {
            return true;
        }

        $this->files->ensureDirectoryExists(dirname($this->path), 0755, true);
        $handle = fopen($this->path, 'c');

        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function isHeld(): bool
    {
        return $this->handle !== null;
    }
}
