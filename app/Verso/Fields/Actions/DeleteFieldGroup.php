<?php

namespace App\Verso\Fields\Actions;

use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Models\FieldGroupRecord;

// Deletes a field group made in the admin. Values already saved in entries stay.
class DeleteFieldGroup
{
    public function __construct(protected FieldGroupRegistry $registry) {}

    public function handle(FieldGroupRecord $record): void
    {
        $record->delete();

        $this->registry->forget();
    }
}
