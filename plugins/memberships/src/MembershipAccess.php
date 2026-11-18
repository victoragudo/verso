<?php

namespace Verso\Plugins\Memberships;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use Verso\Plugins\Memberships\Models\Membership;
use Verso\Plugins\Memberships\Models\Plan;

// Decides who can see restricted entries and members only blocks.
class MembershipAccess
{
    // Panel value for entries open to everyone.
    public const EVERYONE = 'everyone';

    // Panel and block value for "any active member".
    public const ANY_MEMBER = 'members';

    // Entry meta key that holds the restriction.
    public const META_KEY = 'membership';

    public function __construct(protected Settings $settings) {}

    // The plan slug (or "members") an entry needs, or null when it is public.
    public function requirement(Entry $entry): ?string
    {
        $meta = is_array($entry->meta) ? $entry->meta : [];
        $value = $meta[self::META_KEY]['plan'] ?? null;

        return is_string($value) && $value !== '' && $value !== self::EVERYONE ? $value : null;
    }

    public function isRestricted(Entry $entry): bool
    {
        return $this->requirement($entry) !== null;
    }

    public function canView(?User $user, Entry $entry): bool
    {
        return $this->allows($user, $this->requirement($entry));
    }

    // True when the user meets the requirement. Unknown plans count as "any member".
    public function allows(?User $user, ?string $requirement): bool
    {
        if ($requirement === null || $requirement === '' || $requirement === self::EVERYONE) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($user->can((string) config('memberships.bypass_capability'))) {
            return true;
        }

        $level = $this->levelFor($user);

        if ($level === null) {
            return false;
        }

        $plan = $requirement === self::ANY_MEMBER ? null : Plan::query()->where('slug', $requirement)->first();

        return $plan === null || $level >= $plan->level;
    }

    // The highest level among the active memberships of the user, or null.
    public function levelFor(User $user): ?int
    {
        $level = Membership::query()
            ->active()
            ->where('memberships.user_id', $user->id)
            ->join('membership_plans', 'membership_plans.id', '=', 'memberships.plan_id')
            ->max('membership_plans.level');

        return $level === null ? null : (int) $level;
    }

    public function isMember(User $user): bool
    {
        return $this->levelFor($user) !== null;
    }

    // HTML shown in place of hidden content, with login and join links.
    public function teaser(?string $message = null): string
    {
        $joinUrl = (string) $this->settings->get('memberships.join_url', config('memberships.join_url'));
        $default = (string) $this->settings->get('memberships.teaser_message', config('memberships.teaser_message'));

        return view('memberships::teaser', [
            'message' => $message !== null && trim($message) !== '' ? $message : $default,
            'loginUrl' => auth()->check() || ! app('router')->has('login') ? null : route('login'),
            'joinUrl' => $joinUrl !== '' ? $joinUrl : null,
        ])->render();
    }

    /**
     * Empties the children of members only blocks the user may not see. Used for API data.
     *
     * @param  array<mixed>  $blocks
     * @return array<mixed>
     */
    public function stripBlocks(array $blocks, ?User $user): array
    {
        return array_map(function (mixed $block) use ($user): mixed {
            if (! is_array($block)) {
                return $block;
            }

            $children = is_array($block['children'] ?? null) ? $block['children'] : [];
            $hidden = ($block['type'] ?? null) === MembershipsServiceProvider::BLOCK
                && ! $this->allows($user, $this->blockRequirement($block));

            $block['children'] = $hidden ? [] : $this->stripBlocks($children, $user);

            return $block;
        }, $blocks);
    }

    /**
     * The requirement of a members only block: its plan attr or any member.
     *
     * @param  array<mixed>  $block
     */
    public function blockRequirement(array $block): string
    {
        $plan = is_array($block['attrs'] ?? null) ? ($block['attrs']['plan'] ?? null) : null;

        return is_string($plan) && $plan !== '' ? $plan : self::ANY_MEMBER;
    }
}
