<?php

namespace App\Verso\Updates\Jobs;

use App\Verso\System\Server\RuntimeLimits;
use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Models\CoreUpdate;
use App\Verso\Updates\Updater\CoreUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

// Runs a waiting core update, after the response or on the queue.
class RunCoreUpdate implements ShouldQueue
{
    use Queueable;

    // An update must never run twice.
    public int $tries = 1;

    public function __construct(public int $updateId) {}

    public function handle(CoreUpdater $updater, RuntimeLimits $limits): void
    {
        $update = CoreUpdate::query()->find($this->updateId);

        if ($update === null || $update->status !== UpdateStatus::Pending) {
            return;
        }

        ignore_user_abort(true);
        $limits->raiseForHeavyTask();
        set_time_limit(0);

        $updater->run($update);
    }
}
