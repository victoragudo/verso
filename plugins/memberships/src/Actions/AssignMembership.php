<?php

namespace Verso\Plugins\Memberships\Actions;

use App\Models\User;
use Carbon\CarbonInterface;
use Verso\Plugins\Memberships\Enums\MembershipStatus;
use Verso\Plugins\Memberships\Events\MembershipGranted;
use Verso\Plugins\Memberships\Models\Membership;
use Verso\Plugins\Memberships\Models\Plan;

// Gives a user a plan, or renews it. A shop plugin can call this when an order is paid.
class AssignMembership
{
    public function handle(User $user, Plan $plan, ?CarbonInterface $expiresAt = null, ?string $note = null): Membership
    {
        $membership = Membership::query()->updateOrCreate(
            ['user_id' => $user->id, 'plan_id' => $plan->id],
            [
                'status' => MembershipStatus::Active,
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'note' => $note,
            ],
        );

        MembershipGranted::dispatch($membership);

        return $membership;
    }
}
