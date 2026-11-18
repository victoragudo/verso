<?php

namespace App\Verso\Shortcodes\View;

use App\Verso\Shortcodes\ShortcodeExpander;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

// <x-verso-embed name="form" :attrs="['slug' => 'contact']" /> prints any embeddable block in a theme view.
class EmbedComponent extends Component
{
    /**
     * @param  array<string, mixed>  $attrs
     */
    public function __construct(
        public string $name,
        public array $attrs = [],
    ) {}

    public function render(): HtmlString
    {
        return new HtmlString(app(ShortcodeExpander::class)->render($this->name, $this->attrs));
    }
}
