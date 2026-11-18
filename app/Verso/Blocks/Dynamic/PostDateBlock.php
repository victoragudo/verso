<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// The date of the current entry in the site time zone, in a <time> element.
class PostDateBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-date';
    }

    public function label(): string
    {
        return __('Date');
    }

    public function description(): string
    {
        return __('When the post was published or last changed.');
    }

    public function fields(): array
    {
        return [
            new BlockField('which', __('Which date'), BlockFieldType::Select, 'published', ['published' => __('Published'), 'updated' => __('Last changed')]),
            new BlockField('prefix', __('Text before the date'), BlockFieldType::Text, '', help: __('Like "Updated on".'), max: 40),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $date = $this->choice($block, 'which', ['published', 'updated'], 'published') === 'updated' ? $entry->updated_at : ($entry->published_at ?? $entry->created_at);

        if ($date === null) {
            return '';
        }

        $time = app(SiteTime::class);
        $prefix = $this->text($block, 'prefix');

        return '<p class="verso-post-date">'.($prefix !== '' ? e($prefix).' ' : '')
            .'<time datetime="'.e($time->iso($date)).'">'.e($time->local($date)->isoFormat('LL')).'</time></p>';
    }
}
