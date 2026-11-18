<?php

namespace App\Verso\Importer\Models;

use App\Models\User;
use App\Verso\Importer\Enums\ImportStatus;
use Carbon\CarbonInterface;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One import run: the uploaded file, its status and the final report.
 *
 * @property int $id
 * @property string $source
 * @property ImportStatus $status
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property int|null $user_id
 * @property int $processed
 * @property int|null $total
 * @property array<string, mixed>|null $report
 * @property string|null $error
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['source', 'disk', 'path', 'original_name'])]
#[UseFactory(ImportFactory::class)]
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    public const SOURCE_WORDPRESS = 'wordpress';

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'processed' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'report' => 'array',
            'processed' => 'integer',
            'total' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Mark the run as started. A finished run cannot start again.
    public function markRunning(?int $total = null): static
    {
        if ($this->status->isFinished()) {
            throw new LogicException('This import has already finished.');
        }

        $this->status = ImportStatus::Running;
        $this->started_at = now();
        $this->total = $total;
        $this->processed = 0;
        $this->error = null;
        $this->save();

        return $this;
    }

    // Save how many items are done and the report so far.
    /** @param array<string, mixed> $report */
    public function recordProgress(int $processed, array $report): static
    {
        $this->processed = $processed;
        $this->report = $report;
        $this->save();

        return $this;
    }

    /** @param array<string, mixed> $report */
    public function markCompleted(array $report): static
    {
        $this->status = ImportStatus::Completed;
        $this->report = $report;
        $this->finished_at = now();
        $this->save();

        return $this;
    }

    public function markFailed(string $error): static
    {
        $this->status = ImportStatus::Failed;
        $this->error = $error;
        $this->finished_at = now();
        $this->save();

        return $this;
    }

    // How far the run is, from 0 to 100, or null when the total is unknown.
    public function percentDone(): ?int
    {
        if ($this->status === ImportStatus::Completed) {
            return 100;
        }

        if (! $this->total) {
            return null;
        }

        return (int) min(100, floor($this->processed * 100 / $this->total));
    }

    // Seconds the run took, or null when it has not finished.
    public function durationInSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at, true);
    }
}
