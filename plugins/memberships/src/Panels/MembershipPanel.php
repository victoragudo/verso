<?php

namespace Verso\Plugins\Memberships\Panels;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EntryPanel;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\Memberships\MembershipAccess;
use Verso\Plugins\Memberships\Models\Plan;

// The "Access" box in the entry editor: who can read the entry.
class MembershipPanel implements EntryPanel
{
    public function key(): string
    {
        return MembershipAccess::META_KEY;
    }

    public function title(): string
    {
        return __('memberships::Access');
    }

    public function description(): string
    {
        return __('memberships::Visitors without access see the excerpt and a teaser.');
    }

    public function appliesTo(string $contentType): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            new BlockField(
                name: 'plan',
                label: __('memberships::Who can read it'),
                type: BlockFieldType::Select,
                default: MembershipAccess::EVERYONE,
                options: [
                    MembershipAccess::EVERYONE => __('memberships::Everyone'),
                    MembershipAccess::ANY_MEMBER => __('memberships::Any member'),
                    ...Plan::query()->orderBy('level')->get()->mapWithKeys(fn (Plan $plan): array => [$plan->slug => __('memberships:::plan and above', ['plan' => $plan->name])])->all(),
                ],
            ),
        ];
    }
}
