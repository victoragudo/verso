<?php

namespace App\Verso\Updates\Jobs;

use App\Verso\Core\Support\Version;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\UpdateChecker;
use App\Verso\Updates\Support\UpdateSettings;
use App\Verso\Updates\Updater\CoreUpdater;
use Illuminate\Support\Facades\Log;

// The daily update check. It refreshes the cached answer and installs allowed automatic updates.
class CheckForUpdates
{
    public function handle(UpdateChecker $checker, UpdateSettings $settings, CoreUpdater $updater): void
    {
        $release = $checker->check()->availableRelease();
        $current = Version::tryParse($checker->currentVersion());

        if ($release === null || $current === null || ! $settings->automatic()->allows($release, $current)) {
            return;
        }

        try {
            $update = $updater->start($release, UpdateTrigger::Automatic);
        } catch (UpdateFailed $exception) {
            Log::warning("Automatic update to Verso {$release->version} did not start: {$exception->getMessage()}");

            return;
        }

        $updater->run($update);
    }
}
