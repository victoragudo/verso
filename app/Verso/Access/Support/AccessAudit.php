<?php

namespace App\Verso\Access\Support;

use App\Models\User;
use App\Verso\System\Models\ConfigChange;
use Illuminate\Database\Eloquent\Collection;

// Writes role and permission changes to the audit log (the same table as the System settings log).
class AccessAudit
{
    public const ROLE_CREATE = 'role.create';

    public const ROLE_UPDATE = 'role.update';

    public const ROLE_DELETE = 'role.delete';

    public const ROLE_RESET = 'role.reset';

    // A role was given to or taken from a user (key: the role slug).
    public const USER_ROLE = 'user.role';

    // Single grants or denies of a user changed (key: "user:<id>").
    public const USER_ACCESS = 'user.access';

    public const VIEW_AS = 'role.view_as';

    /** @var list<string> */
    public const ACTIONS = [self::ROLE_CREATE, self::ROLE_UPDATE, self::ROLE_DELETE, self::ROLE_RESET, self::USER_ROLE, self::USER_ACCESS, self::VIEW_AS];

    /**
     * @param  array<string, mixed>  $details
     */
    public function log(?User $actor, string $action, string $key, mixed $old, mixed $new, array $details = []): void
    {
        ConfigChange::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'key' => $key,
            'old_value' => $old,
            'new_value' => $new,
            'details' => $details === [] ? null : $details,
        ]);
    }

    // The key of a user line, like "user:12".
    public static function userKey(User $user): string
    {
        return 'user:'.$user->id;
    }

    /**
     * Latest lines about a role, or about every role when $role is null.
     *
     * @return Collection<int, ConfigChange>
     */
    public function forRole(?string $role, int $limit): Collection
    {
        return ConfigChange::query()
            ->with('user:id,name')
            ->whereIn('action', self::ACTIONS)
            ->when($role !== null, fn ($query) => $query->where('key', $role))
            ->latest('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Data for the admin UI.
     *
     * @return array{id: int, action: string, key: string|null, user: string|null, at: string|null, old: mixed, new: mixed, details: array<string, mixed>|null}
     */
    public static function present(ConfigChange $change): array
    {
        return [
            'id' => $change->id,
            'action' => $change->action,
            'key' => $change->key,
            'user' => $change->user?->name,
            'at' => $change->created_at?->toIso8601String(),
            'old' => $change->old_value,
            'new' => $change->new_value,
            'details' => $change->details,
        ];
    }
}
