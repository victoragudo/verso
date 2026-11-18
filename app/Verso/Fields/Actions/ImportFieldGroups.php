<?php

namespace App\Verso\Fields\Actions;

use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Json\FieldGroupJson;
use App\Verso\Fields\Models\FieldGroupRecord;
use Illuminate\Support\Facades\DB;

// Imports field groups from JSON. A group with a known key replaces the saved one.
class ImportFieldGroups
{
    public function __construct(
        protected FieldGroupJson $json,
        protected SaveFieldGroup $save,
    ) {}

    /**
     * @return array{created: list<string>, updated: list<string>}
     */
    public function handle(string $json): array
    {
        $groups = $this->json->decode($json, FieldGroup::SOURCE_DATABASE);

        return DB::transaction(function () use ($groups): array {
            $result = ['created' => [], 'updated' => []];

            foreach ($groups as $group) {
                $record = FieldGroupRecord::query()->where('key', $group->key)->first();
                $result[$record === null ? 'created' : 'updated'][] = $group->key;

                $this->save->handle($group, $record);
            }

            return $result;
        });
    }
}
