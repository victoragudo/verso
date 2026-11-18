<?php

namespace App\Verso\Site\View\Components;

use App\Verso\Content\Models\Entry;
use App\Verso\Site\Seo\SeoBuilder;
use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

// Blade component <x-verso-seo :entry="$entry" /> that prints the SEO tags of a page.
class Seo extends Component
{
    public function __construct(
        protected SeoBuilder $builder,
        protected SeoSettings $settings,
        public ?Entry $entry = null,
        public ?string $title = null,
        public ?string $description = null,
    ) {}

    public function render(): View
    {
        return view('verso-site::seo', [
            'seo' => $this->builder->build($this->entry, $this->title, $this->description),
            'verification' => $this->settings->verificationTags(),
        ]);
    }
}
