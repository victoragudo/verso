<?php

namespace App\Verso\Editor\Http\Requests;

use App\Verso\Access\Roles\Roles;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\Builder\EditorFeatureRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validates the "Editor settings" form: who may use each feature and which blocks each content type allows.
class EditorSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Capability::ManageEditorSettings->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $types = app(ContentTypeRegistry::class)->names();

        return [
            'features' => ['required', 'array:'.implode(',', app(EditorFeatureRegistry::class)->keys())],
            'features.*' => ['array:roles,types'],
            'features.*.roles' => ['present', 'array'],
            'features.*.roles.*' => [Rule::in(app(Roles::class)->slugs())],
            'features.*.types' => ['nullable', 'array'],
            'features.*.types.*' => [Rule::in($types)],
            'blocks' => ['present', 'array:'.implode(',', $types)],
            'blocks.*' => ['nullable', 'array'],
            'blocks.*.*' => [Rule::in(app(BlockRegistry::class)->names())],
        ];
    }

    /** @return array<string, array{roles: list<string>, types: list<string>|null}> */
    public function features(): array
    {
        $features = [];

        foreach ((array) $this->validated('features') as $key => $feature) {
            $features[(string) $key] = [
                'roles' => array_values(array_unique(array_map(strval(...), (array) ($feature['roles'] ?? [])))),
                'types' => is_array($feature['types'] ?? null) ? array_values(array_unique(array_map(strval(...), $feature['types']))) : null,
            ];
        }

        return $features;
    }

    /** @return array<string, list<string>> only the content types that limit their blocks */
    public function blocks(): array
    {
        $blocks = [];

        foreach ((array) $this->validated('blocks') as $type => $names) {
            if (is_array($names)) {
                $blocks[(string) $type] = array_values(array_unique(array_map(strval(...), $names)));
            }
        }

        return $blocks;
    }
}
