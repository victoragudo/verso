<?php

namespace App\Verso\Users\Actions;

use App\Models\User;
use App\Verso\Users\Support\AdminGuard;
use App\Verso\Users\Support\ContentOwnership;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Deletes a user. Their content can move to another user first.
class DeleteUser
{
    public function __construct(
        protected AdminGuard $guard,
        protected ContentOwnership $ownership,
    ) {}

    // Returns the number of content rows given to $reassignTo.
    public function handle(User $user, ?User $reassignTo = null): int
    {
        if ($reassignTo !== null && $reassignTo->is($user)) {
            throw new InvalidArgumentException('Content cannot be given to the user who is being deleted.');
        }

        $this->guard->ensureCanDelete($user);

        return DB::transaction(function () use ($user, $reassignTo): int {
            $moved = $reassignTo === null ? 0 : $this->ownership->reassign($user, $reassignTo);

            $user->delete();

            return $moved;
        });
    }
}
