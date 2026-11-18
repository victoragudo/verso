<?php

namespace App\Verso\Access\Models;

use App\Verso\Core\Database\JsonArray;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A custom role, or the changes made to a built-in role.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property list<string> $capabilities
 * @property list<string>|null $removed_capabilities
 * @property string|null $based_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name', 'description', 'capabilities', 'removed_capabilities', 'based_on'])]
class StoredRole extends Model
{
    protected $table = 'roles';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capabilities' => JsonArray::class,
            'removed_capabilities' => JsonArray::class,
        ];
    }
}
