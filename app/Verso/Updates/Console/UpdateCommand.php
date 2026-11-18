<?php

namespace App\Verso\Updates\Console;

use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateStep;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\UpdateCheck;
use App\Verso\Updates\Feed\UpdateChecker;
use App\Verso\Updates\Models\CoreUpdate;
use App\Verso\Updates\Updater\CoreUpdater;
use Illuminate\Console\Command;

// Checks for, installs or rolls back Verso core updates from the command line.
class UpdateCommand extends Command
{
    protected $signature = 'verso:update
        {--check : Only check the release feed and show the newest version}
        {--yes : Do not ask for confirmation}
        {--rollback : Put back the files and database saved before the last update}';

    protected $description = 'Check for and install Verso core updates, or roll back the last one';

    public function handle(UpdateChecker $checker, CoreUpdater $updater): int
    {
        if ($this->option('rollback')) {
            return $this->rollback($updater);
        }

        if (! $checker->isConfigured()) {
            $this->components->warn('Update checks are off: VERSO_UPDATES_FEED_URL is empty.');

            return self::SUCCESS;
        }

        $check = $checker->check();
        $this->showCheck($check);

        if ($check->error !== null) {
            $this->components->error($check->error);

            return self::FAILURE;
        }

        $release = $check->availableRelease();

        if ($this->option('check') || $release === null) {
            return self::SUCCESS;
        }

        $problem = $updater->databaseBackupProblem();

        if ($problem !== null) {
            $this->components->warn("{$problem} The database will not be backed up.");
        }

        if (! $this->option('yes') && ! $this->confirm("Update Verso from {$check->currentVersion} to {$release->version} now?")) {
            $this->components->info('Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $update = $updater->start($release, UpdateTrigger::Cli, allowWithoutDatabaseBackup: $problem !== null);
        } catch (UpdateFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $update = $updater->run($update);
        $this->showSteps($update);

        return $this->finish($update, "Verso {$update->to_version} is installed.");
    }

    protected function rollback(CoreUpdater $updater): int
    {
        $update = $updater->lastRollbackCandidate();

        if ($update === null) {
            $this->components->warn('There is no completed update with a backup to roll back.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm("Roll back from Verso {$update->to_version} to {$update->from_version}?")) {
            $this->components->info('Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $update = $updater->rollback($update);
        } catch (UpdateFailed $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->showSteps($update);
        $this->components->info("Verso {$update->from_version} is back.");

        return self::SUCCESS;
    }

    protected function showCheck(UpdateCheck $check): void
    {
        $this->components->twoColumnDetail('Running version', $check->currentVersion);
        $this->components->twoColumnDetail('Channel', $check->channel->label());
        $this->components->twoColumnDetail('Newest release', $check->latest->version ?? 'none found');

        if ($check->error === null) {
            $this->components->info($check->updateAvailable()
                ? "Verso {$check->latest?->version} is available."
                : "Verso {$check->currentVersion} is up to date.");
        }
    }

    protected function showSteps(CoreUpdate $update): void
    {
        foreach ($update->steps ?? [] as $entry) {
            $label = UpdateStep::tryFrom($entry['step'])?->label() ?? $entry['step'];
            $this->components->twoColumnDetail($label, strtoupper($entry['status']).($entry['message'] ? ' '.$entry['message'] : ''));
        }
    }

    protected function finish(CoreUpdate $update, string $success): int
    {
        if ($update->status === UpdateStatus::Completed) {
            $this->components->info($success);

            return self::SUCCESS;
        }

        $this->components->error(($update->status === UpdateStatus::RolledBack ? 'The update was rolled back: ' : 'The update failed: ').$update->error);

        return self::FAILURE;
    }
}
