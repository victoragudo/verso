<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;

// A button that switches the site between light and dark colors. The choice is kept in the browser.
// Without a saved choice the site follows the system setting. Themes color it with light-dark().
class ColorSchemeToggleBlock extends SiteBlock
{
    public const NAME = 'color-scheme-toggle';

    // Key of the saved choice in localStorage. The value is "light" or "dark"; no value means "follow the system".
    public const STORAGE_KEY = 'verso-color-scheme';

    // Request attribute set once the click script is printed.
    public const SCRIPT_FLAG = 'verso.color_scheme_script';

    protected const MOON_ICON = '<svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><path d="M16.5 12.2A6.5 6.5 0 0 1 7.8 3.5a6.5 6.5 0 1 0 8.7 8.7Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>';

    protected const SUN_ICON = '<svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><circle cx="10" cy="10" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 1.75v2M10 16.25v2M1.75 10h2M16.25 10h2M4.2 4.2l1.4 1.4M14.4 14.4l1.4 1.4M4.2 15.8l1.4-1.4M14.4 5.6l1.4-1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Dark mode switch');
    }

    public function description(): string
    {
        return __('A button that switches the site between light and dark colors.');
    }

    public function fields(): array
    {
        return [
            new BlockField('darkLabel', __('Text for switching to dark'), BlockFieldType::Text, 'Switch to dark mode', max: 60),
            new BlockField('lightLabel', __('Text for switching to light'), BlockFieldType::Text, 'Switch to light mode', max: 60),
            new BlockField('showText', __('Show the text next to the icon'), BlockFieldType::Toggle, false, help: __('Without it, only screen readers read the text.')),
        ];
    }

    // The tiny script for the page head. It applies the saved choice before the page is painted, so there is no flash.
    public static function headScript(): string
    {
        return trim(view('verso-site::blocks.color-scheme-head')->render());
    }

    protected function html(array $attrs): string
    {
        $textClass = $this->flag($attrs, 'showText') ? 'verso-scheme-toggle__text' : 'visually-hidden';
        $option = fn (string $target, string $icon, string $label): string => '<span class="verso-scheme-toggle__option verso-scheme-toggle__to-'.$target.'">'
            .$icon.'<span class="'.$textClass.'">'.e($label).'</span></span>';

        // Hidden until the script runs, because without JavaScript the button cannot do anything.
        return '<button type="button" class="verso-scheme-toggle" data-verso-scheme-toggle hidden>'
            .$option('dark', self::MOON_ICON, $this->text($attrs, 'darkLabel', 'Switch to dark mode'))
            .$option('light', self::SUN_ICON, $this->text($attrs, 'lightLabel', 'Switch to light mode'))
            .'</button>'.$this->script();
    }

    // The click script once per page.
    protected function script(): string
    {
        $request = request();

        if ($request->attributes->has(self::SCRIPT_FLAG)) {
            return '';
        }

        $request->attributes->set(self::SCRIPT_FLAG, true);

        return view('verso-site::blocks.color-scheme-script')->render();
    }
}
