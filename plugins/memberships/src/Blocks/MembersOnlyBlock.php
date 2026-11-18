<?php

namespace Verso\Plugins\Memberships\Blocks;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Verso\Plugins\Memberships\MembershipAccess;
use Verso\Plugins\Memberships\MembershipsServiceProvider;
use Verso\Plugins\Memberships\Models\Plan;

// A box whose inner blocks only members can see. Others get a teaser.
class MembersOnlyBlock implements HasBlockFields, HasChildBlocks, PluginBlock
{
    public function __construct(protected MembershipAccess $access) {}

    public function name(): string
    {
        return MembershipsServiceProvider::BLOCK;
    }

    public function label(): string
    {
        return __('memberships::Members only');
    }

    public function description(): string
    {
        return __('memberships::Hides the blocks inside it from visitors who are not members.');
    }

    public function fields(): array
    {
        return [
            new BlockField(
                name: 'plan',
                label: __('memberships::Who can see it'),
                type: BlockFieldType::Select,
                default: MembershipAccess::ANY_MEMBER,
                options: [MembershipAccess::ANY_MEMBER => __('memberships::Any member'), ...Plan::query()->orderBy('level')->pluck('name', 'slug')->all()],
            ),
            new BlockField(
                name: 'message',
                label: __('memberships::Teaser text'),
                type: BlockFieldType::Textarea,
                help: __('memberships::Shown to visitors instead of the content. Empty uses the default text.'),
                max: 500,
            ),
        ];
    }

    // Any block can go inside, except columns and another members only box.
    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, [MembershipsServiceProvider::BLOCK, 'columns', 'column'], true);
    }

    public function render(array $block, ?Entry $entry = null): string
    {
        if (! $this->access->allows(auth()->user(), $this->access->blockRequirement($block))) {
            $message = is_array($block['attrs'] ?? null) && is_string($block['attrs']['message'] ?? null) ? $block['attrs']['message'] : null;

            return $this->access->teaser($message);
        }

        return '<div class="verso-members-only">'.app(ContentRenderer::class)->renderChildren($block, $entry).'</div>';
    }
}
