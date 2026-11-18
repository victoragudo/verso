<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use Verso\Plugins\Memberships\Actions\AssignMembership;
use Verso\Plugins\Memberships\Models\Plan;

// Creates the two plans used across the membership tests.
function membershipPlans(): array
{
    return [
        'reader' => Plan::query()->create(['name' => 'Reader', 'description' => 'Weekly essays and the archive.', 'level' => 1]),
        'patron' => Plan::query()->create(['name' => 'Patron', 'description' => 'Everything, plus the print edition.', 'level' => 5]),
    ];
}

// A subscriber user with an active membership of the plan.
function memberOf(Plan $plan, string $name = 'Clara Mendes'): User
{
    $user = User::factory()->create(['name' => $name]);
    app(AssignMembership::class)->handle($user, $plan);

    return $user;
}

// A published post restricted to a plan slug (or "members").
function restrictedPost(string $requirement, array $attributes = []): Entry
{
    return Entry::factory()->published()->create([
        'title' => 'The quiet economics of fishing towns',
        'excerpt' => 'Why small harbors keep their prices low.',
        'blocks' => [['id' => 'b_secret', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Sardine prices in Peniche have not moved since spring.', 'children' => []]],
        'meta' => ['membership' => ['plan' => $requirement]],
        ...$attributes,
    ]);
}
