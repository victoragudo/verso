<?php

namespace App\Verso\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One capability given to (granted) or taken from (denied) a single user, on top of their roles.
 *
 * @property int $user_id
 * @property string $capability
 * @property bool $granted
 */
#[Fillable(['user_id', 'capability', 'granted'])]
class UserCapability extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'capability';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}
