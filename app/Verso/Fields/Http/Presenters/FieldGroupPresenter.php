<?php

namespace App\Verso\Fields\Http\Presenters;

use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Definitions\LocationRule;
use App\Verso\Fields\Enums\FieldPosition;
use App\Verso\Fields\Enums\FieldType;
use App\Verso\Fields\Enums\LocationParam;
use App\Verso\Fields\Models\FieldGroupRecord;
use Closure;

// Turns field groups into plain arrays for the admin pages.
class FieldGroupPresenter
{
    public function __construct(
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected ContentLabels $labels,
    ) {}

    /**
     * One row of the field groups list.
     *
     * @param  array<string, int>  $ids
     * @return array<string, mixed>
     */
    public function row(FieldGroup $group, array $ids): array
    {
        return [
            'id' => $ids[$group->key] ?? null,
            'key' => $group->key,
            'title' => $group->title,
            'description' => $group->description,
            'source' => $group->source,
            'position' => $group->position->value,
            'active' => $group->active,
            'fields' => array_sum(array_map(fn (Field $field): int => $field->count(), $group->fields)),
            'location' => $this->summary($group),
        ];
    }

    // A short sentence that says where the group shows.
    public function summary(FieldGroup $group): string
    {
        $parts = array_map(
            fn (array $rules): string => $this->join(
                array_map($this->ruleText(...), $rules),
                fn (string $first, string $second): string => __(':first and :second', ['first' => $first, 'second' => $second]),
            ),
            $group->location->groups,
        );

        return $this->join(
            $parts,
            fn (string $first, string $second): string => __(':first, or :second', ['first' => $first, 'second' => $second]),
        );
    }

    /**
     * @param  list<string>  $parts
     * @param  Closure(string, string): string  $pair
     */
    protected function join(array $parts, Closure $pair): string
    {
        return array_reduce(array_slice($parts, 1), $pair, $parts[0] ?? '');
    }

    protected function ruleText(LocationRule $rule): string
    {
        $value = match ($rule->param) {
            LocationParam::ContentType => $this->contentTypeLabel($rule->value),
            LocationParam::UserRole => Role::tryFrom($rule->value)?->label() ?? $rule->value,
            LocationParam::TaxonomyTerm => Term::query()->find((int) $rule->value)->name ?? __('term #:id', ['id' => $rule->value]),
            LocationParam::PageTemplate => $rule->value,
        };

        $replace = ['param' => $rule->param->label(), 'value' => $value];

        return $rule->operator === '==' ? __(':param is :value', $replace) : __(':param is not :value', $replace);
    }

    protected function contentTypeLabel(string $name): string
    {
        $type = $this->types->find($name);

        return $type === null ? $name : $this->labels->typeLabel($type);
    }

    /**
     * Choices the builder needs for types, positions and location rules.
     *
     * @return array<string, mixed>
     */
    public function builderOptions(): array
    {
        $contentTypes = array_values(array_map(
            fn (ContentType $type): array => ['value' => $type->name, 'label' => $this->labels->typeLabel($type)],
            $this->types->all(),
        ));
        $taxonomies = array_values(array_map(
            fn (Taxonomy $taxonomy): array => ['value' => $taxonomy->name, 'label' => $this->labels->taxonomyLabel($taxonomy)],
            $this->taxonomies->all(),
        ));
        $roles = array_map(fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()], app(OptionalRoles::class)->available());
        $labels = array_column($taxonomies, 'label', 'value');

        $terms = Term::query()->orderBy('taxonomy')->orderBy('name')->orderBy('id')->get(['id', 'taxonomy', 'name'])
            ->map(fn (Term $term): array => [
                'value' => (string) $term->id,
                'label' => $term->name,
                'group' => $labels[$term->taxonomy] ?? $term->taxonomy,
            ])->all();

        return [
            'fieldTypes' => FieldType::options(),
            'positions' => array_map(fn (FieldPosition $position): array => ['value' => $position->value, 'label' => $position->label()], FieldPosition::cases()),
            'locationParams' => array_map(fn (LocationParam $param): array => ['value' => $param->value, 'label' => $param->label()], LocationParam::cases()),
            'contentTypes' => $contentTypes,
            'taxonomies' => $taxonomies,
            'roles' => $roles,
            'terms' => $terms,
            'maxDepth' => (int) config('verso.fields.max_depth'),
        ];
    }

    /**
     * The group for the builder form.
     *
     * @return array<string, mixed>
     */
    public function form(FieldGroup $group, ?FieldGroupRecord $record): array
    {
        return [...$group->toArray(), 'id' => $record?->id, 'source' => $group->source];
    }
}
