<?php

namespace App\Verso\Blocks\Dynamic;

use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Site\Seo\SeoBuilder;

// Where the page sits in the site: Home, the blog, parent pages, the page. The same trail as the SEO data.
class BreadcrumbsBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'breadcrumbs';
    }

    public function label(): string
    {
        return __('Breadcrumbs');
    }

    public function description(): string
    {
        return __('Links from the home page to the page being shown.');
    }

    public function assets(): array
    {
        return ['breadcrumbs'];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $entry = $this->entry();
        $subject = app(RenderContext::class)->subject();

        if ($entry !== null) {
            $crumbs = array_map(fn (array $item): array => ['name' => (string) $item['name'], 'url' => (string) $item['item']], app(SeoBuilder::class)->breadcrumbs($entry)['itemListElement']);
        } elseif ($subject instanceof Term) {
            $crumbs = [['name' => (string) app(Settings::class)->get('site.name', config('app.name')), 'url' => url('/')], ['name' => $subject->name, 'url' => $subject->url()]];
        } else {
            return '';
        }

        $items = '';
        $last = count($crumbs) - 1;

        foreach ($crumbs as $index => $crumb) {
            $items .= '<li><a href="'.e($crumb['url']).'"'.($index === $last ? ' aria-current="page"' : '').'>'.e($crumb['name']).'</a></li>';
        }

        return '<nav class="verso-breadcrumbs" aria-label="'.e(__('Breadcrumbs')).'"><ol>'.$items.'</ol></nav>';
    }
}
