<?php

namespace Verso\Plugins\Memberships\Actions;

use Verso\Plugins\Memberships\Events\MembershipExpired;
use Verso\Plugins\Memberships\Models\Membership;

// Marks every membership past its end date as expired. Runs once a day.
class ExpireMemberships
{
    // Returns how many memberships were closed.
    public function handle(): int
    {
        $count = 0;

        Membership::query()->due()->each(function (Membership $membership) use (&$count): void {
            $membership->expire();
            MembershipExpired::dispatch($membership);
            $count++;
        });

        return $count;
    }
}
