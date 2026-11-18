<?php

namespace App\Verso\Theme\Templates;

use Closure;

// Lets a module draw a whole page or a part (header, footer) instead of the theme view, like the theme builder does.
// The first override that answers with HTML wins; null lets the theme draw it.
class TemplateOverrides
{
    /** @var array<string, Closure(TemplateCandidates, array<string, mixed>): ?string> */
    protected array $pages = [];

    /** @var array<string, Closure(string, array<string, mixed>, Closure(): string): ?string> */
    protected array $parts = [];

    /** @param Closure(TemplateCandidates, array<string, mixed>): ?string $render */
    public function page(string $key, Closure $render): void
    {
        $this->pages[$key] = $render;
    }

    // The render gets the part name, the view data and a closure that draws the theme part, to wrap it.
    /** @param Closure(string, array<string, mixed>, Closure(): string): ?string $render */
    public function partOverride(string $key, Closure $render): void
    {
        $this->parts[$key] = $render;
    }

    /** @param array<string, mixed> $data */
    public function render(TemplateCandidates $candidates, array $data): ?string
    {
        foreach ($this->pages as $render) {
            $html = $render($candidates, $data);

            if ($html !== null) {
                return $html;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Closure(): string  $theme
     */
    public function part(string $name, array $data, Closure $theme): ?string
    {
        foreach ($this->parts as $render) {
            $html = $render($name, $data, $theme);

            if ($html !== null) {
                return $html;
            }
        }

        return null;
    }
}
