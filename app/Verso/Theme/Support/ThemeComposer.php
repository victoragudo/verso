<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\I18n\Locales;
use App\Verso\Site\Menus\MenuRenderer;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// Gives every theme view the site details, and the layout its navigation and styles.
class ThemeComposer
{
    // Menu location shown in the header. Without a menu there, top level pages are listed.
    public const PRIMARY_MENU = 'primary';

    public function __construct(
        protected Settings $settings,
        protected ThemeManager $themes,
        protected ThemeCustomizer $customizer,
        protected MenuRenderer $menus,
        protected BodyClasses $bodyClasses,
        protected Locales $locales,
    ) {}

    public function compose(View $view): void
    {
        $view->with('site', $this->site());
    }

    public function composeLayout(View $view): void
    {
        $hasPrimaryMenu = $this->menus->hasItems(self::PRIMARY_MENU);
        $entry = $view->getData()['entry'] ?? null;

        $view->with([
            'hasPrimaryMenu' => $hasPrimaryMenu,
            'navigation' => $hasPrimaryMenu ? new Collection : $this->navigation(),
            'themeStyles' => $this->themes->styles()."\n".$this->customizer->css($this->themes->active())."\n".app(GlobalStyles::class)->css($this->themes->active()),
            'themeSettings' => $this->customizer->values($this->themes->active()),
            'themeLogo' => $this->customizer->logo($this->themes->active()),
            'bodyClass' => $this->bodyClasses->for($entry instanceof Entry ? $entry : null),
        ]);
    }

    /**
     * @return array{name: string, tagline: string, locale: string, dir: string}
     */
    public function site(): array
    {
        $locale = str_replace('_', '-', $this->text('site.locale') ?: 'en');

        return [
            'name' => $this->text('site.name'),
            'tagline' => $this->text('site.tagline'),
            'locale' => $locale,
            'dir' => $this->locales->directionOf($locale),
        ];
    }

    /**
     * Top level published pages, in menu order.
     *
     * @return Collection<int, Entry>
     */
    public function navigation(): Collection
    {
        return Entry::query()
            ->ofType('page')
            ->whereNull('parent_id')
            ->published()
            ->orderBy('menu_order')
            ->orderBy('title')
            ->limit(max(0, (int) config('verso.themes.nav_pages')))
            ->get(['id', 'type', 'title', 'slug', 'parent_id', 'status', 'published_at'])
            ->toBase();
    }

    protected function text(string $key): string
    {
        $value = $this->settings->get($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
