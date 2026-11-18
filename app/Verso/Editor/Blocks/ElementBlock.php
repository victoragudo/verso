<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Contracts\RequiresFeature;
use App\Verso\Editor\Rules\ElementAttributesRule;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\ElementPolicy;
use Illuminate\Validation\Rule;

// Any safe HTML element from an allow list, with its own attributes, text and nested blocks.
// Example attrs: {"tag": "nav", "attributes": {"aria-label": "Opening hours"}}.
class ElementBlock extends BaseBlock implements RequiresFeature
{
    public function __construct(protected ElementPolicy $policy) {}

    public function name(): string
    {
        return 'element';
    }

    public function feature(): string
    {
        return 'element_block';
    }

    public function rules(): array
    {
        return [
            'tag' => ['required', 'string', Rule::in(array_keys(ElementPolicy::TAGS))],
            'attributes' => ['sometimes', 'nullable', new ElementAttributesRule($this->policy)],
        ];
    }

    public function acceptsChild(string $type): bool
    {
        return $type !== 'column';
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $tag = $this->policy->allowsTag($block->attrs['tag'] ?? null) ? (string) $block->attrs['tag'] : 'div';
        $text = $renderer->sanitizeInline($block->content);

        return '<'.$tag.$this->policy->render($tag, $block->attrs['attributes'] ?? [], 'verso-element').'>'
            .$text.$renderer->renderChildren($block).'</'.$tag.'>';
    }
}
