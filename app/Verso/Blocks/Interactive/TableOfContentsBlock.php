<?php

namespace App\Verso\Blocks\Interactive;

use App\Verso\Blocks\Support\ModuleBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\CacheableBlock;
use App\Verso\Plugins\Enums\BlockFieldType;
use Illuminate\Support\Str;

// Links to the headings of the page. The block leaves a place holder; when the whole content is drawn,
// fill() gives the headings ids and writes the list. The script marks the part people are reading.
class TableOfContentsBlock extends ModuleBlock implements CacheableBlock
{
    public const NAME = 'table-of-contents';

    // Text the block leaves where the links go.
    public const SLOT = '<!--verso:toc-->';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Table of contents');
    }

    public function description(): string
    {
        return __('Links to the headings of this page.');
    }

    public function category(): string
    {
        return 'text';
    }

    public function fields(): array
    {
        return [
            new BlockField('title', __('Title'), BlockFieldType::Text, '', help: __('Like "On this page".'), max: 80),
            new BlockField('depth', __('Headings to list'), BlockFieldType::Select, '2', ['2' => __('Main headings (H2)'), '3' => __('Main headings and their sub headings (H2 and H3)')]),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $title = $this->text($block, 'title', __('On this page'));
        $depth = $this->choice($block, 'depth', ['2', '3'], '2');

        return '<nav class="verso-toc" aria-label="'.e($title).'" data-verso-toc data-depth="'.$depth.'">'
            .'<p class="verso-toc__title">'.e($title).'</p>'.self::SLOT.'</nav>';
    }

    // Gives the headings ids and fills every table of contents of the HTML. HTML without one comes back unchanged.
    public static function fill(string $html): string
    {
        if (! str_contains($html, self::SLOT)) {
            return $html;
        }

        $depth = preg_match('/data-verso-toc data-depth="3"/', $html) === 1 ? 3 : 2;
        $items = [];
        $used = [];

        $html = (string) preg_replace_callback('/<h([2-'.$depth.'])(\s[^>]*)?>(.*?)<\/h\1>/is', function (array $match) use (&$items, &$used): string {
            $attributes = $match[2];
            $text = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5));

            // Headings inside other blocks of the builder that are not part of the reading flow are skipped.
            if (str_contains($attributes, 'verso-timeline__title') || $text === '') {
                return $match[0];
            }

            if (preg_match('/\sid="([^"]+)"/', $attributes, $existing) === 1) {
                $id = $existing[1];
            } else {
                $base = Str::slug($text) ?: 'section';
                $id = $base;
                $number = 2;

                while (isset($used[$id])) {
                    $id = $base.'-'.$number++;
                }

                $attributes .= ' id="'.e($id).'"';
            }

            $used[$id] = true;
            $items[] = ['id' => $id, 'text' => $text, 'level' => (int) $match[1]];

            return '<h'.$match[1].$attributes.' tabindex="-1">'.$match[3].'</h'.$match[1].'>';
        }, $html);

        return str_replace(self::SLOT, self::list($items), $html);
    }

    /** @param list<array{id: string, text: string, level: int}> $items */
    protected static function list(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<ol class="verso-toc__list">';
        $open = false;

        foreach ($items as $index => $item) {
            if ($item['level'] === 3 && ! $open && $index > 0) {
                $html = substr($html, 0, -5).'<ol>';
                $open = true;
            } elseif ($item['level'] === 2 && $open) {
                $html .= '</ol></li>';
                $open = false;
            }

            $html .= '<li><a href="#'.e($item['id']).'">'.e($item['text']).'</a></li>';
        }

        return $html.($open ? '</ol></li>' : '').'</ol>';
    }
}
