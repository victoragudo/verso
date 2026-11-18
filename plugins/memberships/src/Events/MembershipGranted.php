<?php

namespace Verso\Plugins\Memberships\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Verso\Plugins\Memberships\Models\Membership;

// Sent when a user gets or renews a membership.
class MembershipGranted
{
    use Dispatchable;

    public function __construct(public readonly Membership $membership) {}
}
