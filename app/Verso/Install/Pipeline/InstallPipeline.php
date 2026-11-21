<?php

namespace App\Verso\Install\Pipeline;

use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Pipeline\Steps\CreateAdmin;
use App\Verso\Install\Pipeline\Steps\EnablePlugins;
use App\Verso\Install\Pipeline\Steps\FinishInstall;
use App\Verso\Install\Pipeline\Steps\LinkStorage;
use App\Verso\Install\Pipeline\Steps\PrepareDatabase;
use App\Verso\Install\Pipeline\Steps\RunMigrations;
use App\Verso\Install\Pipeline\Steps\SaveSettings;
use App\Verso\Install\Pipeline\Steps\SeedDemoContent;
use App\Verso\Install\Pipeline\Steps\WriteEnvironment;
use App\Verso\Install\Plan\InstallPlan;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

// Runs the install steps one by one and remembers where it stopped, so a failed step can be retried.
class InstallPipeline
{
    /** @var list<class-string<InstallStep>> */
    public const STEPS = [
        WriteEnvironment::class,
        PrepareDatabase::class,
        RunMigrations::class,
        CreateAdmin::class,
        SaveSettings::class,
        SeedDemoContent::class,
        EnablePlugins::class,
        LinkStorage::class,
        FinishInstall::class,
    ];

    public function __construct(
        protected Container $container,
        protected InstallProgress $progress,
        protected RuntimeConfig $runtime,
        protected DevelopmentServer $server,
        protected int $restartWaitSeconds,
    ) {}

    /**
     * @return list<InstallStep>
     */
    public function steps(): array
    {
        return array_map(fn (string $class): InstallStep => $this->container->make($class), self::STEPS);
    }

    // Start (or resume) an install. Done steps stay done.
    public function start(): PipelineStatus
    {
        $this->progress->start(array_map(fn (InstallStep $step): string => $step->key(), $this->steps()));

        return $this->status();
    }

    // Run the next step that is not done. A failed step is run again.
    public function runNext(InstallPlan $plan): PipelineStatus
    {
        if (! $this->progress->exists()) {
            $this->start();
        }

        $step = $this->nextStep();

        if ($step === null) {
            return $this->status();
        }

        if (! $step instanceof WriteEnvironment && $this->waitsForRestart()) {
            return new PipelineStatus($this->status()->steps, waitingForRestart: true);
        }

        if (! $step->shouldRun($plan)) {
            $this->progress->mark($step->key(), InstallProgress::SKIPPED);

            return $this->status();
        }

        $this->progress->mark($step->key(), InstallProgress::RUNNING);

        try {
            $this->runtime->apply($plan);
            $step->handle($plan);
            $this->progress->mark($step->key(), InstallProgress::DONE);

            if ($step instanceof WriteEnvironment) {
                $this->progress->rememberEnvironmentWrite($this->server->processId(), time());
            }
        } catch (Throwable $exception) {
            $message = $plan->redact($exception->getMessage());
            Log::error('Verso install step "'.$step->key().'" failed: '.$message);
            $this->progress->fail($step->key(), $message);
        }

        return $this->status();
    }

    /**
     * Run every step until the end or the first failure. $onStep is called before each step.
     *
     * @param  (Closure(InstallStep): void)|null  $onStep
     */
    public function runAll(InstallPlan $plan, ?Closure $onStep = null): PipelineStatus
    {
        $this->start();

        while (($step = $this->nextStep()) !== null) {
            if ($onStep !== null && $step->shouldRun($plan)) {
                $onStep($step);
            }

            $status = $this->runNext($plan);

            if ($status->failed()) {
                return $status;
            }
        }

        return $this->status();
    }

    public function status(): PipelineStatus
    {
        $saved = $this->progress->steps();

        return new PipelineStatus(
            steps: array_map(fn (InstallStep $step): array => [
                'key' => $step->key(),
                'label' => $step->label(),
                'status' => $saved[$step->key()] ?? InstallProgress::PENDING,
            ], $this->steps()),
            error: $this->progress->error(),
        );
    }

    // "php artisan serve" restarts soon after .env changes and stops the running request.
    // A step that is cut in half (like half the migrations) cannot be retried, so wait for the
    // new server process first. Without a restart, go on after a few seconds.
    protected function waitsForRestart(): bool
    {
        $write = $this->progress->environmentWrite();

        return $this->server->restartsOnEnvChange()
            && $write !== null
            && $write['process'] === $this->server->processId()
            && time() - $write['at'] < $this->restartWaitSeconds;
    }

    protected function nextStep(): ?InstallStep
    {
        $saved = $this->progress->steps();

        foreach ($this->steps() as $step) {
            if (! in_array($saved[$step->key()] ?? InstallProgress::PENDING, [InstallProgress::DONE, InstallProgress::SKIPPED], true)) {
                return $step;
            }
        }

        return null;
    }
}
