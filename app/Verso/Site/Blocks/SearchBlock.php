<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// A small search form that opens the site search results.
class SearchBlock extends SiteBlock
{
    public function name(): string
    {
        return 'search';
    }

    public function label(): string
    {
        return __('Search');
    }

    public function description(): string
    {
        return __('A search box for your site.');
    }

    public function fields(): array
    {
        return [
            new BlockField('placeholder', __('Hint inside the box'), BlockFieldType::Text, 'Search', max: 80),
            new BlockField('buttonLabel', __('Button text'), BlockFieldType::Text, 'Search', max: 40),
        ];
    }

    protected function html(array $attrs): string
    {
        $button = e($this->text($attrs, 'buttonLabel', 'Search'));

        return '<form class="verso-search" role="search" action="'.e(route('theme.search')).'" method="get">'
            .'<label><span class="visually-hidden">'.$button.'</span>'
            .'<input type="search" name="q" placeholder="'.e($this->text($attrs, 'placeholder', 'Search')).'" autocomplete="off"></label>'
            .'<button type="submit">'.$button.'</button></form>';
    }
}
