<?php

namespace App\Verso\Updates\Updater;

use App\Models\User;
use App\Verso\Core\Support\Version;
use App\Verso\I18n\Translate;
use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateStep;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\Release;
use App\Verso\Updates\Models\CoreUpdate;
use Illuminate\Filesystem\Filesystem;
use Throwable;

// Runs a core update step by step, logs every step and rolls back files and database when a step fails.
class CoreUpdater
{
    public function __construct(
        protected PackageDownloader $downloader,
        protected PackageVerifier $verifier,
        protected PackageUnpacker $unpacker,
        protected CoreFiles $coreFiles,
        protected DatabaseBackup $database,
        protected ArtisanRunner $artisan,
        protected HealthCheck $health,
        protected UpdateLock $lock,
        protected Filesystem $files,
        protected string $installPath,
        protected string $storagePath,
        protected string $currentVersion,
        protected int $backupsKept,
        protected int $maintenanceRetry,
    ) {}

    // Null when the database can be backed up, or the reason why not.
    public function databaseBackupProblem(): ?string
    {
        return $this->database->unavailableReason();
    }

    // Create a waiting update run. It refuses a second run, an older release and a missing database backup without consent.
    public function start(Release $release, UpdateTrigger $trigger, ?User $user = null, bool $allowWithoutDatabaseBackup = false): CoreUpdate
    {
        if (CoreUpdate::query()->unfinished()->exists()) {
            throw UpdateFailed::because(__('Another update is already waiting or running.'));
        }

        if (! $release->semver()->isNewerThan(Version::parse($this->currentVersion))) {
            throw UpdateFailed::because(__('Verso :version is not newer than the running version :current.', ['version' => $release->version, 'current' => $this->currentVersion]));
        }

        $problem = $this->database->unavailableReason();

        if ($problem !== null && ! $allowWithoutDatabaseBackup) {
            throw UpdateFailed::because(__(':problem Confirm that you want to update without a database backup.', ['problem' => $problem]));
        }

        $update = new CoreUpdate([
            'from_version' => $this->currentVersion,
            'to_version' => $release->version,
            'trigger' => $trigger,
            'release' => $release->toArray(),
            'allow_without_database_backup' => $allowWithoutDatabaseBackup,
        ]);
        $update->user()->associate($user);
        $update->save();

        return $update;
    }

    // Run every step of a waiting update. It never throws: the result is in the run's status and log.
    public function run(CoreUpdate $update): CoreUpdate
    {
        if ($update->status !== UpdateStatus::Pending) {
            return $update;
        }

        $update->markRunning();
        $state = new UpdateRunState;

        try {
            $this->step($update, UpdateStep::Lock, function (): string {
                if (! $this->lock->acquire()) {
                    throw UpdateFailed::because(__('Another update is running right now.'));
                }

                return __('Lock taken.');
            });

            $release = $update->releaseData() ?? throw UpdateFailed::because(__('The release data of this update is broken.'));
            $this->runSteps($update, $release, $state);
            $update->markCompleted();
        } catch (Throwable $exception) {
            $this->fail($update, $state, $exception);
        } finally {
            $this->lock->release();
        }

        return $update;
    }

    // Put back the files and database saved before a completed update.
    public function rollback(CoreUpdate $update): CoreUpdate
    {
        if ($update->status !== UpdateStatus::Completed) {
            throw UpdateFailed::because(__('Only a completed update can be rolled back.'));
        }

        $backup = $this->backupDirectory($update);

        if ($update->backup_path === null || ! $this->coreFiles->hasBackup($backup)) {
            throw UpdateFailed::because(__('The backup of this update is gone, so it cannot be rolled back.'));
        }

        if (! $this->lock->acquire()) {
            throw UpdateFailed::because(__('Another update is running right now.'));
        }

        try {
            $state = new UpdateRunState;
            $state->maintenance = false;
            $state->filesTouched = true;
            $state->databaseChanged = true;
            $state->databaseBackup = $this->databaseBackupFile($backup);
            $state->backupDirectory = $backup;

            $this->rollBackChanges($update, $state);
            $update->markRolledBack(__('Rolled back to Verso :version by hand.', ['version' => $update->from_version]));
        } finally {
            $this->lock->release();
        }

        return $update;
    }

    // The newest completed update that still has its backup.
    public function lastRollbackCandidate(): ?CoreUpdate
    {
        return CoreUpdate::query()
            ->where('status', UpdateStatus::Completed->value)
            ->whereNotNull('backup_path')
            ->latest('id')
            ->first();
    }

    protected function runSteps(CoreUpdate $update, Release $release, UpdateRunState $state): void
    {
        $work = $this->workDirectory($update);
        $backup = $this->backupDirectory($update);
        $zip = '';
        $source = '';

        $this->step($update, UpdateStep::Download, function () use ($release, $work, &$zip): string {
            $zip = $this->downloader->download($release, $work.'/download');

            return Translate::icu('{count, plural, one {Downloaded {file} (# byte).} other {Downloaded {file} (# bytes).}}', ['file' => basename($zip), 'count' => (int) filesize($zip)]);
        });

        $this->step($update, UpdateStep::Verify, fn (): string => $this->verify($release, $zip));

        $this->step($update, UpdateStep::Extract, function () use ($release, $zip, $work, &$source): string {
            $source = $this->unpacker->unpack($zip, $work.'/package', $release->version);

            return __('Package holds Verso :version.', ['version' => $release->version]);
        });

        $this->step($update, UpdateStep::Maintenance, function () use ($state): string {
            $this->artisan->run('down', ['--retry='.$this->maintenanceRetry]);
            $state->maintenance = true;

            return __('Visitors see the maintenance page.');
        });

        $state->backupDirectory = $backup;
        $update->backup_path = $backup;
        $update->save();

        $this->backUpDatabase($update, $state, $backup);

        $paths = $this->coreFiles->plan($source);

        $this->step($update, UpdateStep::BackupFiles, function () use ($paths, $backup, $state): string {
            $state->filesTouched = true;
            $this->coreFiles->backup($this->installPath, $paths, $backup);

            return count($paths).' core paths saved.';
        });

        $this->step($update, UpdateStep::ReplaceFiles, function () use ($source, $paths): string {
            $this->coreFiles->install($source, $this->installPath, $paths);

            return Translate::icu('{count, plural, one {Copied # core path. Settings, uploads, plugins and themes were kept.} other {Copied # core paths. Settings, uploads, plugins and themes were kept.}}', ['count' => count($paths)]);
        });

        $this->step($update, UpdateStep::Migrate, function () use ($state): string {
            $state->databaseChanged = true;

            return $this->artisan->run('migrate', ['--force']) ?: __('Nothing to migrate.');
        });

        $this->step($update, UpdateStep::ClearCaches, function (): string {
            $this->artisan->run('optimize:clear');

            return __('Config, route, view and data caches cleared.');
        });

        $this->step($update, UpdateStep::Online, function () use ($state): string {
            $this->artisan->run('up');
            $state->maintenance = false;

            return __('The site is live again.');
        });

        $this->step($update, UpdateStep::HealthCheck, fn (): string => $this->health->check());

        $this->step($update, UpdateStep::Cleanup, function () use ($work, $update): string {
            $this->files->deleteDirectory($work);
            $removed = $this->pruneBackups($update);

            return $removed > 0 ? Translate::icu('{count, plural, one {Removed # old backup.} other {Removed # old backups.}}', ['count' => $removed]) : __('Temporary files removed.');
        });
    }

    protected function verify(Release $release, string $zip): string
    {
        if ($release->checksumUrl === null) {
            throw UpdateFailed::because(__('This release has no sha256 file, so it cannot be checked.'));
        }

        $this->verifier->verifyChecksum($zip, $this->downloader->fetchText($release->checksumUrl, __('the sha256 file')));

        if (! $this->verifier->requiresSignature()) {
            return __('The sha256 sum matches. No public key is set, so the signature was not checked.');
        }

        if ($release->signatureUrl === null) {
            throw UpdateFailed::because(__('A public key is set, but this release has no signature.'));
        }

        $this->verifier->verifySignature($zip, $this->downloader->fetchText($release->signatureUrl, __('the signature file')));

        return __('The sha256 sum and the Ed25519 signature match.');
    }

    protected function backUpDatabase(CoreUpdate $update, UpdateRunState $state, string $backup): void
    {
        $problem = $this->database->unavailableReason();

        if ($problem !== null) {
            if (! $update->allow_without_database_backup) {
                $update->logStep(UpdateStep::BackupDatabase, CoreUpdate::STEP_FAILED, $problem);

                throw UpdateFailed::because($problem);
            }

            $update->logStep(UpdateStep::BackupDatabase, CoreUpdate::STEP_WARNING, "{$problem} The update goes on without a database backup, as confirmed.");

            return;
        }

        $this->step($update, UpdateStep::BackupDatabase, function () use ($backup, $state): string {
            $state->databaseBackup = $this->database->backup($backup);

            return __('Saved :file.', ['file' => basename($state->databaseBackup)]);
        });
    }

    // Log a step as running, run it, then log it as done or failed.
    /** @param callable(): string $callback */
    protected function step(CoreUpdate $update, UpdateStep $step, callable $callback): void
    {
        $update->logStep($step, CoreUpdate::STEP_RUNNING);

        try {
            $message = $callback();
        } catch (Throwable $exception) {
            $update->logStep($step, CoreUpdate::STEP_FAILED, $this->messageFor($exception));

            throw $exception;
        }

        $update->logStep($step, CoreUpdate::STEP_DONE, $message);
    }

    protected function fail(CoreUpdate $update, UpdateRunState $state, Throwable $exception): void
    {
        $error = $this->messageFor($exception);

        if (! $state->filesTouched && ! $state->databaseChanged) {
            if ($state->maintenance) {
                $this->bringOnline($state);
            }

            $update->markFailed($error);

            return;
        }

        try {
            $this->rollBackChanges($update, $state);
            $update->markRolledBack($error);
        } catch (Throwable $rollbackError) {
            $update->logStep(UpdateStep::Rollback, CoreUpdate::STEP_FAILED, $this->messageFor($rollbackError));
            $update->persistAll();
            $update->markFailed("{$error} The rollback failed too ({$this->messageFor($rollbackError)}). The backup is in {$state->backupDirectory}.");
        }
    }

    protected function rollBackChanges(CoreUpdate $update, UpdateRunState $state): void
    {
        $update->logStep(UpdateStep::Rollback, CoreUpdate::STEP_RUNNING);

        if (! $state->maintenance) {
            $this->artisan->run('down', ['--retry='.$this->maintenanceRetry]);
            $state->maintenance = true;
        }

        $restored = [];

        if ($state->filesTouched && $state->backupDirectory !== null) {
            $this->coreFiles->restore($this->installPath, $state->backupDirectory);
            $restored[] = 'files';
        }

        if ($state->databaseChanged && $state->databaseBackup !== null) {
            $this->database->restore($state->databaseBackup);
            $update->persistAll();
            $restored[] = 'database';
        }

        $this->artisan->run('optimize:clear');
        $this->bringOnline($state);

        $update->logStep(UpdateStep::Rollback, CoreUpdate::STEP_DONE, Translate::icu('{restored, select, files {Restored the core files.} database {Restored the database.} both {Restored the core files and the database.} other {Nothing needed to be restored.}} The site runs Verso {version} again.', [
            'restored' => count($restored) === 2 ? 'both' : ($restored[0] ?? 'none'),
            'version' => $update->from_version,
        ]));
    }

    // Leave maintenance mode. A failure here is not fatal, the error is already logged.
    protected function bringOnline(UpdateRunState $state): void
    {
        try {
            $this->artisan->run('up');
            $state->maintenance = false;
        } catch (UpdateFailed) {
        }
    }

    // Delete backups older than the newest ones kept.
    protected function pruneBackups(CoreUpdate $current): int
    {
        $old = CoreUpdate::query()
            ->whereNotNull('backup_path')
            ->whereKeyNot($current->id)
            ->latest('id')
            ->get()
            ->all();
        $old = array_slice($old, max(0, $this->backupsKept - 1));

        foreach ($old as $update) {
            $this->files->deleteDirectory($this->backupDirectory($update));
            $update->backup_path = null;
            $update->save();
        }

        return count($old);
    }

    protected function databaseBackupFile(string $backup): ?string
    {
        foreach (['database.sqlite', 'database.sql'] as $name) {
            if ($this->files->exists($backup.'/'.$name)) {
                return $backup.'/'.$name;
            }
        }

        return null;
    }

    protected function workDirectory(CoreUpdate $update): string
    {
        return $this->storagePath.'/runs/'.$update->id;
    }

    protected function backupDirectory(CoreUpdate $update): string
    {
        return $this->storagePath.'/backups/'.$update->id;
    }

    protected function messageFor(Throwable $exception): string
    {
        return $exception instanceof UpdateFailed
            ? $exception->getMessage()
            : __('Unexpected error: :message', ['message' => mb_substr($exception->getMessage(), 0, 500)]);
    }
}
