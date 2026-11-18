<?php

namespace App\Verso\Fields\Actions;

use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Models\FieldGroupRecord;

// Saves a checked field group in the database, as a new row or over an existing one.
class SaveFieldGroup
{
    public function __construct(protected FieldGroupRegistry $registry) {}

    public function handle(FieldGroup $group, ?FieldGroupRecord $record = null): FieldGroupRecord
    {
        $record ??= new FieldGroupRecord;
        $record->applyDefinition($group)->save();

        $this->registry->forget();

        return $record;
    }
}
