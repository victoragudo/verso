<?php

namespace App\Verso\Plugins\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\BaseBlock;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\HasParentBlocks;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Plugins\Contracts\RendersWithRenderer;

// Lets the core block registry validate and render a plugin block.
class PluginBlockAdapter extends BaseBlock
{
    public function __construct(protected PluginBlock $block) {}

    public function name(): string
    {
        return $this->block->name();
    }

    // Rules come from the block fields. Blocks without fields accept any attrs.
    public function rules(): array
    {
        if (! $this->block instanceof HasBlockFields) {
            return [];
        }

        $rules = [];

        foreach ($this->block->fields() as $field) {
            $rules[$field->name] = $field->rules();
        }

        return $rules;
    }

    // Embeddable blocks can ask for a capability, like the html block does.
    public function capability(): ?string
    {
        return $this->block instanceof EmbeddableBlock ? $this->block->capability() : null;
    }

    /** @return list<string>|null the block types this block may sit in, null for anywhere */
    public function parents(): ?array
    {
        return $this->block instanceof HasParentBlocks ? $this->block->parents() : null;
    }

    public function plugin(): PluginBlock
    {
        return $this->block;
    }

    // Only blocks that implement HasChildBlocks hold children.
    public function acceptsChild(string $type): bool
    {
        return $this->block instanceof HasChildBlocks && $this->block->acceptsChild($type);
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        if ($this->block instanceof RendersWithRenderer) {
            return $this->block->renderWith($block, $renderer);
        }

        return $this->block->render([
            'id' => $block->id,
            'type' => $block->type,
            'attrs' => $block->attrs,
            'content' => $block->content,
            'children' => $block->children,
        ]);
    }
}
