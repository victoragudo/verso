<?php

namespace Verso\Plugins\Forms\View;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Verso\Plugins\Forms\Models\Form;

// <x-forms-form slug="contact" /> prints a form in a theme view. An unknown slug prints nothing.
class FormComponent extends Component
{
    public function __construct(
        public string $slug,
        public string $heading = '',
        public string $intro = '',
    ) {}

    public function render(): HtmlString
    {
        $form = Form::query()->where('slug', $this->slug)->first();

        // An HtmlString is printed as it is. A plain string would be compiled again as Blade.
        return new HtmlString(app(FormRenderer::class)->render($form, $this->heading, $this->intro));
    }
}
