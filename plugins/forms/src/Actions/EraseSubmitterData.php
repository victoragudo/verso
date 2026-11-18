<?php

namespace Verso\Plugins\Forms\Actions;

use Verso\Plugins\Forms\Models\Submission;

// Privacy request: deletes every submission (and its files) sent from one email address.
class EraseSubmitterData
{
    // Returns how many submissions were deleted.
    public function handle(string $email): int
    {
        $email = mb_strtolower(trim($email));
        $deleted = 0;

        Submission::query()->where('email', $email)->orderBy('id')->each(function (Submission $submission) use (&$deleted): void {
            $submission->delete();
            $deleted++;
        });

        return $deleted;
    }
}
