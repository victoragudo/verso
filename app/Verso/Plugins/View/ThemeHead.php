<?php

namespace App\Verso\Plugins\View;

use App\Verso\Plugins\Filters\HeadTagsFilter;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

// Blade component <x-verso-head /> that prints the tags plugins add to the <head> of public pages.
class ThemeHead extends Component
{
    public function __construct(protected Request $request) {}

    public function render(): HtmlString
    {
        $tags = array_map(fn (HeadTag $tag): string => $tag->toHtml(), HeadTagsFilter::apply([], $this->request));

        return new HtmlString(implode("\n", $tags));
    }
}
