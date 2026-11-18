<?php

namespace App\Verso\Plugins\View;

use App\Verso\Plugins\Filters\ThemeFooterFilter;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

// Blade component <x-verso-footer /> that prints the HTML plugins add at the end of public pages.
class ThemeFooter extends Component
{
    // Request attribute set once the footer is printed, so it is not added a second time.
    public const PRINTED = 'verso.theme_footer_printed';

    public function __construct(protected Request $request) {}

    // The plugin HTML is printed as it is, never compiled as Blade.
    public function render(): HtmlString
    {
        $this->request->attributes->set(self::PRINTED, true);

        return new HtmlString(ThemeFooterFilter::apply('', $this->request));
    }
}
