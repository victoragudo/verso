<?php

namespace App\Verso\Theme\View\Components;

use App\Verso\Theme\Templates\TemplateParts;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

// Blade component <x-verso-part-blocks part="header" key="header-centered" /> for theme parts built with blocks.
// The blocks live in parts/<key>.json of the theme (or a parent), the same file the Site Editor starts from.
// "Create child theme from these changes" writes parts this way.
class PartBlocks extends Component
{
    public function __construct(
        protected ThemeManager $themes,
        protected TemplateParts $parts,
        public string $part,
        public ?string $key = null,
    ) {}

    public function render(): HtmlString
    {
        $key = $this->key ?? $this->part;

        if (preg_match(TemplateParts::NAME_PATTERN, $this->part) !== 1 || preg_match(TemplateParts::NAME_PATTERN, $key) !== 1) {
            return new HtmlString('');
        }

        $file = $this->themes->locateFile($this->themes->active(), TemplateParts::BLOCKS_FOLDER.'/'.$key.'.json');
        $decoded = $file === null ? null : json_decode((string) file_get_contents($file), true);
        $blocks = is_array($decoded) && is_array($decoded['blocks'] ?? null) ? array_values($decoded['blocks']) : [];

        return new HtmlString($blocks === [] ? '' : $this->parts->renderStored($this->part, $key, $blocks));
    }
}
