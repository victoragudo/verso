<?php

namespace App\Verso\Updates\Updater;

// What an update run has changed so far, so a rollback knows what to put back.
final class UpdateRunState
{
    public bool $maintenance = false;

    public bool $filesTouched = false;

    public bool $databaseChanged = false;

    public ?string $backupDirectory = null;

    public ?string $databaseBackup = null;
}
