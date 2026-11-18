<?php

namespace App\Verso\Layouts;

use App\Verso\Blocks\Interactive\TableOfContentsBlock;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Theme\Templates\TemplateCandidates;
use App\Verso\Theme\Templates\TemplateContext;
use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;

// Draws theme builder layouts in place of the theme views: templates inside the theme page layout,
// headers and footers in place of the theme parts.
class LayoutRenderer
{
    // Pages a template can replace. Parts and fixed views (like the shop cart) stay with the theme.
    protected const array TEMPLATE_PAGES = [
        TemplateContext::Entry, TemplateContext::FrontPage, TemplateContext::Archive, TemplateContext::Taxonomy,
        TemplateContext::Author, TemplateContext::Home, TemplateContext::Search, TemplateContext::NotFound,
    ];

    // Asset key of the header and footer styles and script.
    public const string HEADER_ASSET = 'layout-header';

    public function __construct(
        protected LayoutResolver $resolver,
        protected BlockRenderer $blocks,
        protected ViewFactory $views,
    ) {}

    /** @param array<string, mixed> $data */
    public function template(TemplateCandidates $candidates, array $data): ?string
    {
        if (! in_array($candidates->context, self::TEMPLATE_PAGES, true) || $this->blocks->showsMarkers()) {
            return null;
        }

        $layout = $this->resolver->best(LayoutKind::Template);

        if ($layout === null) {
            return null;
        }

        $settings = LayoutSettings::clean(LayoutKind::Template, $layout->published_settings ?? []);
        $html = '<div class="verso-layout verso-layout--'.e($settings['width']).'" data-verso-layout="'.$layout->id.'">'
            .TableOfContentsBlock::fill($this->blocks->render($layout->published_blocks ?? [])).'</div>';

        return $this->views->make('verso-layouts::template', [...$data, 'layoutHtml' => $html])->render();
    }

    /**
     * A header or footer layout in place of the theme part. Top bars always go above the header.
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(): string  $theme
     */
    public function part(string $name, array $data, Closure $theme): ?string
    {
        $kind = LayoutKind::tryFrom($name);

        if (! in_array($kind, [LayoutKind::Header, LayoutKind::Footer], true)) {
            return null;
        }

        $layout = $this->resolver->best($kind);
        $before = $kind === LayoutKind::Header ? app(PopupRenderer::class)->topBars() : '';

        if ($layout === null) {
            return $before === '' ? null : $before.$theme();
        }

        return $before.$this->bar($layout, $kind);
    }

    protected function bar(SiteLayout $layout, LayoutKind $kind): string
    {
        $settings = LayoutSettings::clean($kind, $layout->published_settings ?? []);
        $tag = $kind === LayoutKind::Header ? 'header' : 'footer';
        $classes = ['site-part', 'site-part-'.$tag, 'verso-'.$tag];

        foreach (['sticky', 'transparent'] as $flag) {
            if (($settings[$flag] ?? false) === true) {
                $classes[] = 'verso-'.$tag.'--'.$flag;
            }
        }

        $attributes = ' data-site-part="layout-'.$layout->id.'"';

        if ($kind === LayoutKind::Header) {
            $attributes .= ' data-verso-header data-scroll="'.e($settings['scrollEffect']).'" data-offset="'.(int) $settings['scrollOffset'].'"';
        }

        $assets = $this->blocks->assetsFor([self::HEADER_ASSET]);

        return $assets['before'].'<'.$tag.' class="'.implode(' ', $classes).'"'.$attributes.'>'
            .$this->blocks->render($layout->published_blocks ?? []).'</'.$tag.'>'.$assets['after'];
    }
}
