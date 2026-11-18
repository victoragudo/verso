<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Core\Css\CssValueRule;
use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Support\ThemeComposer;
use App\Verso\Theme\ThemeManager;

// The site logo from the theme settings, linked to the home page. Shows the site name when there is no logo.
class SiteLogoBlock extends SiteBlock
{
    // Height in pixels when none is set, and the range a pixel height is kept in.
    protected const int DEFAULT_HEIGHT = 44;

    protected const int MIN_HEIGHT = 16;

    protected const int MAX_HEIGHT = 240;

    public function __construct(
        UrlSanitizer $urls,
        protected ThemeCustomizer $customizer,
        protected ThemeManager $themes,
        protected ThemeComposer $composer,
    ) {
        parent::__construct($urls);
    }

    public function name(): string
    {
        return 'site-logo';
    }

    public function label(): string
    {
        return __('Logo');
    }

    public function description(): string
    {
        return __('Your logo, linked to the home page.');
    }

    public function fields(): array
    {
        return [
            $this->heightField(),
            new BlockField('linkHome', __('Link to the home page'), BlockFieldType::Toggle, true),
        ];
    }

    protected function html(array $attrs): string
    {
        $name = $this->composer->site()['name'];
        $logo = $this->customizer->logo($this->themes->active());
        $css = $this->heightField()->css($attrs['height'] ?? null) ?? self::DEFAULT_HEIGHT.'px';
        $pixels = preg_match('/^(\d+(?:\.\d+)?)px$/', $css, $match) === 1 ? max(self::MIN_HEIGHT, min(self::MAX_HEIGHT, (int) round((float) $match[1]))) : null;

        if ($logo === null) {
            $inner = '<span class="verso-site-logo__name">'.e($name).'</span>';
        } elseif ($pixels !== null) {
            $src = $logo->isResizable() ? $logo->imageUrl(['h' => $pixels * 2]) : $logo->url();
            $width = $logo->width && $logo->height ? ' width="'.(int) round($pixels * $logo->width / $logo->height).'"' : '';
            $inner = '<img src="'.e($src).'" alt="'.e($name).'" height="'.$pixels.'"'.$width.'>';
        } else {
            $src = $logo->isResizable() ? $logo->imageUrl(['h' => self::MAX_HEIGHT * 2]) : $logo->url();
            $inner = '<img src="'.e($src).'" alt="'.e($name).'" style="height:'.e($css).';width:auto">';
        }

        return $this->flag($attrs, 'linkHome', true)
            ? '<a class="verso-site-logo" href="'.e(url('/')).'" rel="home">'.$inner.'</a>'
            : '<div class="verso-site-logo">'.$inner.'</div>';
    }

    // The height field: any CSS size, plain old numbers count as pixels.
    protected function heightField(): BlockField
    {
        return new BlockField('height', __('Height'), BlockFieldType::Length, self::DEFAULT_HEIGHT.'px', help: __('Logo height, like 44px or 3rem.'), value: new CssValueRule(negative: false));
    }
}
