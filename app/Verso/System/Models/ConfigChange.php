<?php

namespace App\Verso\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of the System settings audit log.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $key
 * @property mixed $old_value
 * @property mixed $new_value
 * @property array<string, mixed>|null $details
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'action', 'key', 'old_value', 'new_value', 'details'])]
class ConfigChange extends Model
{
    public const UPDATE = 'update';

    public const RESET = 'reset';

    public const INI_WRITE = 'ini_write';

    public const PERMISSIONS_FIX = 'permissions_fix';

    protected $table = 'system_config_changes';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'details' => 'array',
        ];
    }
}
