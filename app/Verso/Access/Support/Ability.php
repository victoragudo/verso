<?php

namespace App\Verso\Access\Support;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use Closure;
use Illuminate\Support\Facades\Gate;

// One check for nav items, dashboard widgets and slots: a capability, a Gate ability with arguments,
// or a closure that gets the user.
final class Ability
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function allows(?User $user, Capability|string|Closure|null $ability, array $arguments = []): bool
    {
        if ($ability === null) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($ability instanceof Closure) {
            return (bool) $ability($user);
        }

        if ($ability instanceof Capability) {
            return $user->hasCapability($ability);
        }

        return Gate::forUser($user)->allows($ability, $arguments);
    }

    // The key sent to the admin UI, like "manage_users".
    public static function key(Capability|string|Closure|null $ability): ?string
    {
        return $ability instanceof Capability ? $ability->value : (is_string($ability) ? $ability : null);
    }
}
