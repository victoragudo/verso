<?php

namespace App\Verso\Fields\Models;

use App\Verso\Core\Database\JsonArray;
use App\Verso\Fields\Definitions\FieldGroup;
use Database\Factories\FieldGroupRecordFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A field group saved from the admin. Its fields and rules are read through toDefinition().
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string|null $description
 * @property string $position
 * @property int $menu_order
 * @property bool $active
 * @property array<mixed> $location
 * @property array<mixed> $fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(FieldGroupRecordFactory::class)]
class FieldGroupRecord extends Model
{
    /** @use HasFactory<FieldGroupRecordFactory> */
    use HasFactory;

    protected $table = 'field_groups';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'menu_order' => 'integer',
            'active' => 'boolean',
            'location' => JsonArray::class,
            'fields' => JsonArray::class,
        ];
    }

    // Copies a checked definition into the columns. The definition is the only way in.
    public function applyDefinition(FieldGroup $group): static
    {
        $data = $group->toArray();

        $this->key = $data['key'];
        $this->title = $data['title'];
        $this->description = $data['description'] === '' ? null : $data['description'];
        $this->position = $data['position'];
        $this->menu_order = $data['order'];
        $this->active = $data['active'];
        $this->location = $data['location'];
        $this->fields = $data['fields'];

        return $this;
    }

    public function toDefinition(): FieldGroup
    {
        return FieldGroup::fromArray([
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'order' => $this->menu_order,
            'active' => $this->active,
            'location' => $this->location,
            'fields' => $this->fields,
        ], FieldGroup::SOURCE_DATABASE);
    }
}
