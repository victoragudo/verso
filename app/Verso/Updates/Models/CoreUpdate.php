<?php

namespace App\Verso\Updates\Models;

use App\Models\User;
use App\Verso\Updates\Enums\UpdateStatus;
use App\Verso\Updates\Enums\UpdateStep;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Feed\Release;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * One core update run: versions, status and the log of its steps.
 *
 * @property int $id
 * @property string $from_version
 * @property string $to_version
 * @property UpdateStatus $status
 * @property UpdateTrigger $trigger
 * @property int|null $user_id
 * @property array<string, mixed> $release
 * @property list<array{step: string, status: string, message: string|null, at: string}>|null $steps
 * @property string|null $backup_path
 * @property bool $allow_without_database_backup
 * @property string|null $error
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class CoreUpdate extends Model
{
    public const STEP_RUNNING = 'running';

    public const STEP_DONE = 'done';

    public const STEP_FAILED = 'failed';

    public const STEP_SKIPPED = 'skipped';

    public const STEP_WARNING = 'warning';

    protected $table = 'updates';

    /** @var list<string> */
    protected $fillable = ['from_version', 'to_version', 'trigger', 'release', 'allow_without_database_backup'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'allow_without_database_backup' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UpdateStatus::class,
            'trigger' => UpdateTrigger::class,
            'release' => 'array',
            'steps' => 'array',
            'allow_without_database_backup' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Runs that are waiting or running. Only one may exist at a time.
    /** @param Builder<self> $query */
    public function scopeUnfinished(Builder $query): void
    {
        $query->whereIn('status', [UpdateStatus::Pending->value, UpdateStatus::Running->value]);
    }

    public function releaseData(): ?Release
    {
        return Release::fromArray($this->release);
    }

    public function markRunning(): static
    {
        $this->status = UpdateStatus::Running;
        $this->started_at = now();
        $this->error = null;
        $this->save();

        return $this;
    }

    // Add a step to the log, or change it when it is already there.
    public function logStep(UpdateStep $step, string $status, ?string $message = null): static
    {
        $steps = $this->steps ?? [];
        $entry = ['step' => $step->value, 'status' => $status, 'message' => $message, 'at' => now()->toIso8601String()];
        $found = false;

        foreach ($steps as $index => $existing) {
            if ($existing['step'] === $step->value) {
                $steps[$index] = $entry;
                $found = true;
            }
        }

        if (! $found) {
            $steps[] = $entry;
        }

        $this->steps = $steps;
        $this->save();

        return $this;
    }

    // The status of a step in the log, or null when it has not run.
    public function stepStatus(UpdateStep $step): ?string
    {
        foreach ($this->steps ?? [] as $entry) {
            if ($entry['step'] === $step->value) {
                return $entry['status'];
            }
        }

        return null;
    }

    public function markCompleted(): static
    {
        return $this->finish(UpdateStatus::Completed);
    }

    public function markFailed(string $error): static
    {
        return $this->finish(UpdateStatus::Failed, $error);
    }

    public function markRolledBack(string $error): static
    {
        return $this->finish(UpdateStatus::RolledBack, $error);
    }

    // Write every column again. Used after a database restore put back an older copy of this row.
    public function persistAll(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->newQueryWithoutScopes()->whereKey($this->getKey())->update(Arr::except($this->getAttributes(), [$this->getKeyName()]));
        $this->syncOriginal();
    }

    protected function finish(UpdateStatus $status, ?string $error = null): static
    {
        $this->status = $status;
        $this->error = $error;
        $this->finished_at = now();
        $this->save();

        return $this;
    }
}
