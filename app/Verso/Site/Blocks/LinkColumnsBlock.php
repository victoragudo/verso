<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// Columns of links with a title each, for footers. Written as text: a title line ends with ":", links are "Label | URL".
class LinkColumnsBlock extends SiteBlock
{
    public function name(): string
    {
        return 'link-columns';
    }

    public function label(): string
    {
        return __('Link columns');
    }

    public function description(): string
    {
        return __('Groups of links with a title, for the footer.');
    }

    public function fields(): array
    {
        return [
            new BlockField('columns', __('Links'), BlockFieldType::Textarea, '', help: __('Start each group with a title that ends with ":", then one link per line, like "About us | /about".'), max: 4000),
        ];
    }

    protected function html(array $attrs): string
    {
        $html = '';

        foreach ($this->groups($this->text($attrs, 'columns')) as $group) {
            $links = '';

            foreach ($this->linkLines(implode("\n", $group['lines'])) as $link) {
                $links .= '<li>'.$this->link($link['url'], $link['label']).'</li>';
            }

            if ($links !== '') {
                $html .= '<div class="verso-link-columns__group">'.($group['title'] !== '' ? '<h2>'.e($group['title']).'</h2>' : '').'<ul>'.$links.'</ul></div>';
            }
        }

        return $html === '' ? '' : '<nav class="verso-link-columns" aria-label="Footer">'.$html.'</nav>';
    }

    /**
     * @return list<array{title: string, lines: list<string>}>
     */
    protected function groups(string $text): array
    {
        $groups = [];
        $title = null;
        $lines = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_ends_with($line, ':') && ! str_contains($line, '|')) {
                if ($title !== null || $lines !== []) {
                    $groups[] = ['title' => $title ?? '', 'lines' => $lines];
                }

                $title = rtrim($line, ': ');
                $lines = [];

                continue;
            }

            $lines[] = $line;
        }

        if ($title !== null || $lines !== []) {
            $groups[] = ['title' => $title ?? '', 'lines' => $lines];
        }

        return $groups;
    }
}
