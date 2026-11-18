<?php

namespace App\Verso\Importer\Http\Presenters;

use App\Verso\Importer\Models\Import;

// Turns an import run into the data the admin pages show.
class ImportPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Import $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'original_name' => $import->original_name,
            'processed' => $import->processed,
            'total' => $import->total,
            'percent' => $import->percentDone(),
            'user' => $import->user?->name,
            'created_at' => $import->created_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Import $import): array
    {
        return [
            ...self::summary($import),
            'report' => $import->report,
            'error' => $import->error,
            'started_at' => $import->started_at?->toIso8601String(),
            'duration' => $import->durationInSeconds(),
        ];
    }
}
