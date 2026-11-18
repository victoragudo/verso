<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use App\Verso\Theme\Support\ThemeComposer;

// The site name from the general settings.
class SiteTitleBlock extends SiteBlock
{
    public function __construct(UrlSanitizer $urls, protected ThemeComposer $composer)
    {
        parent::__construct($urls);
    }

    public function name(): string
    {
        return 'site-title';
    }

    public function label(): string
    {
        return __('Site name');
    }

    public function description(): string
    {
        return __('The name of your site, from the settings.');
    }

    public function fields(): array
    {
        return [new BlockField('linkHome', __('Link to the home page'), BlockFieldType::Toggle, true)];
    }

    protected function html(array $attrs): string
    {
        $name = e($this->composer->site()['name']);

        return '<p class="verso-site-title">'
            .($this->flag($attrs, 'linkHome', true) ? '<a href="'.e(url('/')).'" rel="home">'.$name.'</a>' : $name)
            .'</p>';
    }
}
