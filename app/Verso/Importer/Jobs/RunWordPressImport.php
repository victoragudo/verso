<?php

namespace App\Verso\Importer\Jobs;

use App\Models\User;
use App\Verso\Importer\Models\Import;
use App\Verso\Importer\Support\ImportReport;
use App\Verso\Importer\WordPress\WordPressImporter;
use App\Verso\System\Server\RuntimeLimits;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use Throwable;

// Runs an uploaded WordPress import in the queue and keeps its status up to date.
class RunWordPressImport implements ShouldQueue
{
    use Dispatchable, Localizable, Queueable;

    // An import is never retried on its own: a second try would only repeat the same error.
    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $importId)
    {
        $this->timeout = (int) config('verso.importer.job_timeout');
        $queue = config('verso.importer.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(WordPressImporter $importer, RuntimeLimits $limits): void
    {
        $import = Import::query()->find($this->importId);

        if ($import === null || $import->status->isFinished()) {
            return;
        }

        $limits->raiseForHeavyTask();
        $this->withLocale($import->user?->preferredLocale(), fn () => $this->run($import, $importer));
    }

    protected function run(Import $import, WordPressImporter $importer): void
    {
        $temporary = null;

        try {
            [$path, $temporary] = $this->localFile($import);
            $import->markRunning($importer->countItems($path));

            $every = max(1, (int) config('verso.importer.progress_every'));
            $report = $importer->import($path, $this->owner($import), function (int $done, ImportReport $report) use ($import, $every): void {
                if ($done % $every === 0) {
                    $import->recordProgress($done, $report->toArray());
                }
            });

            $import->processed = (int) $import->total;
            $import->markCompleted($report->toArray());
            Storage::disk($import->disk)->delete($import->path);
        } catch (Throwable $exception) {
            $import->markFailed($exception->getMessage());
            report($exception);
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }
    }

    // Called by the queue when the job dies, for example after a timeout.
    public function failed(?Throwable $exception): void
    {
        $import = Import::query()->find($this->importId);

        if ($import !== null && ! $import->status->isFinished()) {
            $this->withLocale($import->user?->preferredLocale(), fn () => $import->markFailed($exception?->getMessage() ?? __('The import stopped before it finished.')));
        }
    }

    // The person who uploaded the file owns posts with unknown authors.
    protected function owner(Import $import): User
    {
        return $import->user ?? throw new RuntimeException(__('The user who started this import no longer exists.'));
    }

    /**
     * A local path to the export file. Files on cloud disks are copied to a temporary file first.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function localFile(Import $import): array
    {
        $disk = Storage::disk($import->disk);

        if (config("filesystems.disks.{$import->disk}.driver") === 'local') {
            return [$disk->path($import->path), null];
        }

        $stream = $disk->readStream($import->path) ?? throw new RuntimeException(__('The uploaded file is missing.'));
        $temporary = (string) tempnam(sys_get_temp_dir(), 'verso-wxr');
        file_put_contents($temporary, $stream);

        return [$temporary, $temporary];
    }
}
