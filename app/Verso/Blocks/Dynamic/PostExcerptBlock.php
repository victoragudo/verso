<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Plugins\Enums\BlockFieldType;
use Illuminate\Support\Str;

// The excerpt of the current entry, or the start of its text when it has none.
class PostExcerptBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'post-excerpt';
    }

    public function label(): string
    {
        return __('Excerpt');
    }

    public function description(): string
    {
        return __('A short summary of the post or page being shown.');
    }

    public function fields(): array
    {
        return [
            new BlockField('words', __('Most words'), BlockFieldType::Number, 40, min: 5, max: 200),
            new BlockField('more', __('"Read more" link text'), BlockFieldType::Text, '', max: 60),
        ];
    }

    protected function forEntry(Entry $entry, BlockData $block, BlockRenderer $renderer): string
    {
        $text = trim((string) $entry->excerpt);

        if ($text === '') {
            $text = trim(html_entity_decode(strip_tags(app(ContentRenderer::class)->render($entry)), ENT_QUOTES | ENT_HTML5));
        }

        $text = Str::words(preg_replace('/\s+/', ' ', $text) ?? '', (int) $this->number($block, 'words', 40, 5, 200));
        $more = $this->text($block, 'more');

        return $text === '' ? '' : '<p class="verso-post-excerpt">'.e($text)
            .($more !== '' ? ' <a href="'.e($entry->url()).'">'.e($more).'<span class="verso-visually-hidden">: '.e($entry->title).'</span></a>' : '').'</p>';
    }
}
