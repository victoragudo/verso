<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// The author of the current entry, linked to the author page.
class PostAuthorBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-author';
    }

    public function label(): string
    {
        return __('Author');
    }

    public function description(): string
    {
        return __('Who wrote the post, with a link to their page.');
    }

    public function fields(): array
    {
        return [
            new BlockField('prefix', __('Text before the name'), BlockFieldType::Text, '', help: __('Like "By".'), max: 40),
            new BlockField('bio', __('Show the bio'), BlockFieldType::Toggle, false),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $author = $entry->author;

        if ($author === null) {
            return '';
        }

        $url = $author->slug !== null && $author->slug !== '' ? url(config('verso.users.profile.author_base').'/'.$author->slug) : null;
        $name = $url === null ? e($author->name) : '<a href="'.e($url).'" rel="author">'.e($author->name).'</a>';
        $prefix = $this->text($block, 'prefix');
        $bio = $block->bool('bio') && is_string($author->bio ?? null) && $author->bio !== '' ? '<p class="verso-post-author__bio">'.e($author->bio).'</p>' : '';

        return '<div class="verso-post-author"><p>'.($prefix !== '' ? e($prefix).' ' : '').$name.'</p>'.$bio.'</div>';
    }
}
