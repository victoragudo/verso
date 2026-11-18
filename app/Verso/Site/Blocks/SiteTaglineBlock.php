<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Theme\Support\ThemeComposer;

// The short site description from the general settings. Prints nothing when it is empty.
class SiteTaglineBlock extends SiteBlock
{
    public function __construct(UrlSanitizer $urls, protected ThemeComposer $composer)
    {
        parent::__construct($urls);
    }

    public function name(): string
    {
        return 'site-tagline';
    }

    public function label(): string
    {
        return __('Tagline');
    }

    public function description(): string
    {
        return __('The short description of your site, from the settings.');
    }

    public function fields(): array
    {
        return [];
    }

    protected function html(array $attrs): string
    {
        $tagline = $this->composer->site()['tagline'];

        return $tagline === '' ? '' : '<p class="verso-site-tagline">'.e($tagline).'</p>';
    }
}
