<?php

namespace App\Verso\Content\Policies;

use App\Models\User;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Enums\Capability;

// Only users with the manage terms capability can change terms.
class TermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(Capability::ManageTerms);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(Capability::ManageTerms);
    }

    public function update(User $user, Term $term): bool
    {
        return $user->hasCapability(Capability::ManageTerms);
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->hasCapability(Capability::ManageTerms);
    }
}
