<?php

namespace App\Verso\Editor\CustomCode;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\View\HeadTag;

// Prints custom code on public pages: CSS in a <style> tag, JavaScript as an external deferred file.
class CustomCodeOutput
{
    public function __construct(protected CustomCodeStore $store) {}

    /**
     * Adds the site wide CSS to the head.
     *
     * @param  array<mixed>  $tags
     * @return list<mixed>
     */
    public function headTags(array $tags): array
    {
        $css = $this->store->find(null)->css ?? null;

        return $css === null ? array_values($tags) : [...array_values($tags), HeadTag::style($css)];
    }

    // Adds the site wide script file before </body>.
    public function footer(string $html): string
    {
        return $html.$this->script(null);
    }

    // Adds the CSS and script file of one entry around its content.
    public function entryContent(string $html, Entry $entry): string
    {
        $code = $this->store->find($entry->id);

        if ($code === null) {
            return $html;
        }

        $style = $code->css === null ? '' : '<style data-verso-page-css>'.str_ireplace('</style', '<\/style', $code->css).'</style>';

        return $style.$html.$this->script($entry->id);
    }

    protected function script(?int $entryId): string
    {
        $code = $this->store->find($entryId);

        if ($code === null || $code->js === null) {
            return '';
        }

        $url = route('verso.custom-script', ['scope' => $entryId === null ? 'site' : 'entry-'.$entryId, 'v' => $code->version()]);

        return '<script src="'.e($url).'" defer></script>';
    }
}
