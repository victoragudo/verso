<?php

namespace App\Verso\Blocks\Dynamic;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Term;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// The title of a list page: the category name, the content type, the author or the search words.
class ArchiveTitleBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'archive-title';
    }

    public function label(): string
    {
        return __('Title of the list');
    }

    public function description(): string
    {
        return __('The name of the category, author or search on list pages.');
    }

    public function fields(): array
    {
        return [new BlockField('level', __('Heading level'), BlockFieldType::Select, '1', ['1' => 'H1', '2' => 'H2'])];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $render = app(RenderContext::class);
        $subject = $render->subject();
        $title = match (true) {
            $subject instanceof Term => $subject->name,
            $subject instanceof ContentType => __($subject->pluralLabel),
            $subject instanceof User => $subject->name,
            $render->page() === 'search' => __('Search results for “:words”', ['words' => (string) $render->data('search', '')]),
            $render->page() === '404' => __('Page not found'),
            default => '',
        };

        if ($title === '') {
            return '';
        }

        $tag = 'h'.$this->choice($block, 'level', ['1', '2'], '1');

        return '<'.$tag.' class="verso-archive-title">'.e($title).'</'.$tag.'>';
    }
}
