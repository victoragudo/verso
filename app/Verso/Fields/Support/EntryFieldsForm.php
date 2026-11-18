<?php

namespace App\Verso\Fields\Support;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Definitions\LocationContext;
use App\Verso\Fields\FieldGroupRegistry;
use App\Verso\Fields\Values\FieldValidation;
use App\Verso\Fields\Values\FieldValidator;

// Connects field groups to the entry editor: which groups to show and how to check what was sent.
class EntryFieldsForm
{
    public function __construct(
        protected FieldGroupRegistry $groups,
        protected FieldValidator $validator,
    ) {}

    /**
     * Groups the editor may show. Template and term rules are checked again in the browser while editing.
     *
     * @return list<FieldGroup>
     */
    public function editorGroups(string $contentType, ?User $user): array
    {
        return $this->groups->matching(new LocationContext(
            contentType: $contentType,
            template: false,
            termIds: null,
            role: $user?->role->value ?? '',
            roles: $user?->roleSlugs() ?? [],
        ));
    }

    /**
     * Checks meta.fields of a request. The values keep stored fields of groups that do not show now.
     *
     * @param  array<array-key, mixed>  $meta
     * @param  list<int>  $termIds
     */
    public function validate(string $contentType, ?User $user, array $meta, array $termIds, ?Entry $entry = null): FieldValidation
    {
        $fields = $this->groups->fields(new LocationContext(
            contentType: $contentType,
            template: is_string($meta['template'] ?? null) ? $meta['template'] : '',
            termIds: $termIds,
            role: $user?->role->value ?? '',
            roles: $user?->roleSlugs() ?? [],
        ));

        $input = is_array($meta['fields'] ?? null) ? $meta['fields'] : [];
        $result = $this->validator->validate(array_values($fields), $input);

        $stored = is_array($entry?->meta['fields'] ?? null) ? $entry->meta['fields'] : [];
        $kept = array_diff_key($stored, $fields);

        return new FieldValidation([...$kept, ...$result->values], $result->errors);
    }
}
