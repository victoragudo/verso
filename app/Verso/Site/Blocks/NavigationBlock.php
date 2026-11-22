<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Menus\MenuRenderer;

// The menu shown in one place of the site (main menu, footer menu...). On small screens it folds into a "Menu" button.
class NavigationBlock extends SiteBlock
{
    // Request attribute set once the fit script is printed.
    public const SCRIPT_FLAG = 'verso.navigation_script';

    protected const MENU_ICON = '<svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

    protected const CHEVRON_ICON = '<svg aria-hidden="true" viewBox="0 0 12 12" width="12" height="12"><path d="M3 4.5 6 7.5l3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    public function __construct(
        UrlSanitizer $urls,
        protected MenuRenderer $menus,
        protected MenuLocations $locations,
    ) {
        parent::__construct($urls);
    }

    public function name(): string
    {
        return 'navigation';
    }

    public function label(): string
    {
        return __('Menu');
    }

    public function description(): string
    {
        return __('Links from one of your menus.');
    }

    public function fields(): array
    {
        $places = $this->locations->translated();

        return [
            new BlockField('location', __('Which menu'), BlockFieldType::Select, array_key_first($places) ?? 'primary', $places === [] ? ['primary' => __('Primary menu')] : $places, __('Pick the menu to show. Edit its links in Site, Menus.')),
            new BlockField('label', __('Name for screen readers'), BlockFieldType::Text, 'Main', max: 60),
            new BlockField('collapse', __('Fold into a "Menu" button on phones'), BlockFieldType::Toggle, true),
        ];
    }

    protected function html(array $attrs): string
    {
        $location = $this->text($attrs, 'location', 'primary');

        if (! $this->locations->has($location)) {
            return '';
        }

        $links = $this->menus->links($location, request()->url());

        if ($links === []) {
            return '';
        }

        $id = 'verso-nav-'.$location;
        $collapse = $this->flag($attrs, 'collapse', true);
        $list = '<ul id="'.e($id).'" class="verso-nav__list"'.($collapse ? ' popover="auto"' : '').'>'.$this->items($links, $id).'</ul>';
        $button = $collapse
            ? '<button class="verso-nav__toggle" type="button" popovertarget="'.e($id).'" aria-controls="'.e($id).'">'.self::MENU_ICON.'<span>'.e(__('Menu')).'</span></button>'
            : '';

        return '<nav class="verso-nav'.($collapse ? '' : ' is-wide').'" aria-label="'.e($this->textOrTranslated($attrs, 'label', 'Main', __('Main'))).'"'.($collapse ? ' data-verso-nav' : '').'>'
            .$button.$list.'</nav>'.($collapse ? $this->script() : '');
    }

    // The fit script once per page: inline links when they fit, the "Menu" popover when they do not.
    protected function script(): string
    {
        $request = request();

        if ($request->attributes->has(self::SCRIPT_FLAG)) {
            return '';
        }

        $request->attributes->set(self::SCRIPT_FLAG, true);

        return view('verso-site::blocks.navigation-script')->render();
    }

    /**
     * @param  list<mixed>  $links
     */
    protected function items(array $links, string $prefix): string
    {
        $html = '';

        foreach ($links as $index => $link) {
            if (! is_array($link) || ! is_string($link['url'] ?? null) || ! is_string($link['label'] ?? null)) {
                continue;
            }

            $children = is_array($link['children'] ?? null) ? $link['children'] : [];
            $current = ($link['active'] ?? false) === true ? ' aria-current="page"' : '';
            $rel = MenuRenderer::rel($link);
            $newTab = (($link['new_tab'] ?? false) === true ? ' target="_blank"' : '').($rel !== null ? ' rel="'.e($rel).'"' : '');

            $submenu = $prefix.'-'.$index;
            $more = $children === [] ? '' : '<button class="verso-nav__more" type="button" aria-expanded="false" aria-controls="'.e($submenu).'" data-verso-submenu>'
                .'<span class="visually-hidden">'.e(__('More in :name', ['name' => $link['label']])).'</span>'.self::CHEVRON_ICON.'</button>'
                .'<ul id="'.e($submenu).'" class="verso-nav__submenu">'.$this->items(array_values($children), $submenu).'</ul>';

            $classes = trim(($children !== [] ? 'has-children' : '').(($link['mega'] ?? false) === true ? ' is-mega' : ''));
            $html .= '<li'.($classes !== '' ? ' class="'.$classes.'"' : '').'><a href="'.e($link['url']).'"'.$current.$newTab.'>'.e($link['label']).'</a>'.$more.'</li>';
        }

        return $html;
    }
}
