<?php

namespace Verso\Plugins\Forms\Actions;

use Verso\Plugins\Forms\Models\Submission;
use Verso\Plugins\Forms\Support\FormsSettings;

// Deletes submissions older than the retention period. A period of 0 days keeps everything.
class PruneSubmissions
{
    public function __construct(protected FormsSettings $settings) {}

    // Returns how many submissions were deleted.
    public function handle(): int
    {
        $days = $this->settings->retentionDays();

        if ($days === 0) {
            return 0;
        }

        $deleted = 0;

        Submission::query()->where('created_at', '<', now()->subDays($days))->orderBy('id')->each(function (Submission $submission) use (&$deleted): void {
            $submission->delete();
            $deleted++;
        });

        return $deleted;
    }
}
