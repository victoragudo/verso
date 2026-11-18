<?php

namespace Verso\Plugins\Shop\View;

use Illuminate\View\View;

// Adds the shop CSS after the theme CSS on every public page, so product blocks look right anywhere.
class ShopStylesComposer
{
    protected ?string $css = null;

    public function __construct(protected string $path) {}

    public function compose(View $view): void
    {
        $styles = $view->getData()['themeStyles'] ?? '';

        $view->with('themeStyles', (is_string($styles) ? $styles : '')."\n".$this->css());
    }

    protected function css(): string
    {
        return $this->css ??= is_file($this->path) ? (string) file_get_contents($this->path) : '';
    }
}
