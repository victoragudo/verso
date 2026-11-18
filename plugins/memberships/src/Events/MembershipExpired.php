<?php

namespace Verso\Plugins\Memberships\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Verso\Plugins\Memberships\Models\Membership;

// Sent when the daily job closes a membership that reached its end date.
class MembershipExpired
{
    use Dispatchable;

    public function __construct(public readonly Membership $membership) {}
}
