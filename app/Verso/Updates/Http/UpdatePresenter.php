<?php

namespace App\Verso\Updates\Http;

use App\Verso\Updates\Enums\UpdateStep;
use App\Verso\Updates\Feed\Release;
use App\Verso\Updates\Models\CoreUpdate;
use App\Verso\Updates\Support\ReleaseNotes;

// Shapes releases and update runs for the admin pages.
class UpdatePresenter
{
    /**
     * @return array{version: string, name: string, notes_html: string, published_at: string|null, pre_release: bool, security: bool, page_url: string|null, package_size: int|null, signed: bool}
     */
    public static function release(Release $release): array
    {
        return [
            'version' => $release->version,
            'name' => $release->name,
            'notes_html' => ReleaseNotes::toHtml($release->notes),
            'published_at' => $release->publishedAt,
            'pre_release' => $release->preRelease,
            'security' => $release->security,
            'page_url' => $release->pageUrl,
            'package_size' => $release->packageSize,
            'signed' => $release->signatureUrl !== null,
        ];
    }

    /**
     * One row of the update log.
     *
     * @return array{id: int, from_version: string, to_version: string, status: string, status_label: string, trigger: string, trigger_label: string, user: string|null, error: string|null, created_at: string|null, started_at: string|null, finished_at: string|null, has_backup: bool}
     */
    public static function summary(CoreUpdate $update): array
    {
        return [
            'id' => $update->id,
            'from_version' => $update->from_version,
            'to_version' => $update->to_version,
            'status' => $update->status->value,
            'status_label' => $update->status->label(),
            'trigger' => $update->trigger->value,
            'trigger_label' => $update->trigger->label(),
            'user' => $update->user?->name,
            'error' => $update->error,
            'created_at' => $update->created_at?->toIso8601String(),
            'started_at' => $update->started_at?->toIso8601String(),
            'finished_at' => $update->finished_at?->toIso8601String(),
            'has_backup' => $update->backup_path !== null,
        ];
    }

    /**
     * A run with every planned step and what happened in it.
     *
     * @return array<string, mixed>
     */
    public static function detail(CoreUpdate $update): array
    {
        $logged = [];

        foreach ($update->steps ?? [] as $entry) {
            $logged[$entry['step']] = $entry;
        }

        $steps = UpdateStep::plan();

        if (isset($logged[UpdateStep::Rollback->value])) {
            $steps[] = UpdateStep::Rollback;
        }

        return [
            ...self::summary($update),
            'finished' => $update->status->isFinished(),
            'steps' => array_map(fn (UpdateStep $step): array => [
                'key' => $step->value,
                'label' => $step->label(),
                'status' => $logged[$step->value]['status'] ?? 'pending',
                'message' => $logged[$step->value]['message'] ?? null,
                'at' => $logged[$step->value]['at'] ?? null,
            ], $steps),
        ];
    }
}
