<?php

namespace App\Verso\Editor\Blocks;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;

// A simple table. attrs.rows is a list of rows, each row a list of inline HTML cells.
class TableBlock extends BaseBlock
{
    public function name(): string
    {
        return 'table';
    }

    public function rules(): array
    {
        return [
            'rows' => ['nullable', 'array'],
            'rows.*' => ['array'],
            'rows.*.*' => ['nullable', 'string', 'max:5000'],
            'header' => ['nullable', 'boolean'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function render(BlockData $block, BlockRenderer $renderer): string
    {
        $rows = array_values(array_filter($block->list('rows'), is_array(...)));

        if ($rows === []) {
            return '';
        }

        $head = '';

        if ($block->bool('header')) {
            $head = '<thead>'.$this->row(array_shift($rows), 'th', $renderer).'</thead>';
        }

        $body = implode('', array_map(fn (array $row): string => $this->row($row, 'td', $renderer), $rows));

        return '<figure class="verso-table"><table>'.$head.($body === '' ? '' : '<tbody>'.$body.'</tbody>').'</table>'
            .$this->caption($block->string('caption'), $renderer).'</figure>';
    }

    /** @param array<mixed> $cells */
    protected function row(array $cells, string $tag, BlockRenderer $renderer): string
    {
        $html = '';

        foreach ($cells as $cell) {
            $html .= "<{$tag}>".(is_string($cell) ? $renderer->sanitizeInline($cell) : '')."</{$tag}>";
        }

        return '<tr>'.$html.'</tr>';
    }
}
