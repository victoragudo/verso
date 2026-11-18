<?php

namespace App\Verso\Site\Blocks;

use App\Verso\Editor\Support\UrlSanitizer;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Enums\BlockFieldType;
use App\Verso\Theme\Support\ThemeComposer;
use Illuminate\Support\Carbon;

// A copyright line. {year} becomes the current year and {site} the site name, so it never gets old.
class CopyrightBlock extends SiteBlock
{
    public const DEFAULT_TEXT = '© {year} {site}. All rights reserved.';

    public function __construct(UrlSanitizer $urls, protected ThemeComposer $composer)
    {
        parent::__construct($urls);
    }

    public function name(): string
    {
        return 'copyright';
    }

    public function label(): string
    {
        return __('Copyright');
    }

    public function description(): string
    {
        return __('A copyright line with the year always up to date.');
    }

    public function fields(): array
    {
        return [
            new BlockField('text', __('Text'), BlockFieldType::Text, self::DEFAULT_TEXT, help: __('{year} shows the current year and {site} your site name.'), max: 300),
        ];
    }

    protected function html(array $attrs): string
    {
        $text = strtr($this->text($attrs, 'text', self::DEFAULT_TEXT), [
            '{year}' => (string) Carbon::now()->year,
            '{site}' => $this->composer->site()['name'],
        ]);

        return '<p class="verso-copyright">'.e($text).'</p>';
    }
}
