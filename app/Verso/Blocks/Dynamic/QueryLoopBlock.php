<?php

namespace App\Verso\Blocks\Dynamic;

use App\Models\User;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockData;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Media\ResponsiveImages;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\HasChildBlocks;
use App\Verso\Plugins\Contracts\HasStarterBlocks;
use App\Verso\Plugins\Enums\BlockFieldType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

// A list of entries drawn with the blocks inside it, once per entry: a blog list, the latest recipes, related posts.
// It shows the entries of the page (on archives and search) or its own query, with filters, order and pages.
class QueryLoopBlock extends DynamicBlock implements HasChildBlocks, HasStarterBlocks
{
    public const NAME = 'query-loop';

    public const ORDERS = ['date', 'title', 'updated', 'menu', 'random'];

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return __('Query loop');
    }

    public function description(): string
    {
        return __('A list of posts or pages drawn with the blocks inside, with filters and pages.');
    }

    public function assets(): array
    {
        return ['query-loop'];
    }

    public function acceptsChild(string $type): bool
    {
        return ! in_array($type, ['column', self::NAME], true);
    }

    public function starter(): array
    {
        return [
            ['type' => 'post-featured-image', 'attrs' => ['link' => true]],
            ['type' => 'post-title', 'attrs' => ['level' => '3', 'link' => true]],
            ['type' => 'post-date'],
            ['type' => 'post-excerpt', 'attrs' => ['words' => 25]],
        ];
    }

    public function fields(): array
    {
        $types = [];

        foreach (app(ContentTypeRegistry::class)->all() as $type) {
            $types[$type->name] = __($type->pluralLabel);
        }

        $terms = ['' => __('Any')];

        foreach (Term::query()->orderBy('taxonomy')->orderBy('name')->limit((int) config('verso.layouts.query_choices'))->get(['id', 'name', 'taxonomy']) as $term) {
            $terms[(string) $term->id] = $term->name.' ('.$term->taxonomy.')';
        }

        $authors = ['' => __('Anyone')];

        foreach (User::query()->whereIn('id', Entry::query()->select('author_id'))->orderBy('name')->limit((int) config('verso.layouts.query_choices'))->get(['id', 'name']) as $user) {
            $authors[(string) $user->id] = $user->name;
        }

        return [
            new BlockField('source', __('Which entries'), BlockFieldType::Select, 'custom', ['custom' => __('Pick them with the filters below'), 'page' => __('The entries of this page (archives and search)')]),
            new BlockField('type', __('Content type'), BlockFieldType::Select, 'post', $types === [] ? ['post' => __('Posts')] : $types),
            new BlockField('term', __('Category or tag'), BlockFieldType::Select, '', $terms, searchable: true),
            new BlockField('author', __('Author'), BlockFieldType::Select, '', $authors, searchable: true),
            new BlockField('order', __('Order'), BlockFieldType::Select, 'date', ['date' => __('Newest first'), 'title' => __('By title, A to Z'), 'updated' => __('Last changed first'), 'menu' => __('Menu order'), 'random' => __('Random')]),
            new BlockField('count', __('How many'), BlockFieldType::Number, 6, min: 1, max: (int) config('verso.layouts.query_max')),
            new BlockField('offset', __('Skip the first'), BlockFieldType::Number, 0, min: 0, max: 100),
            new BlockField('excludeCurrent', __('Leave out the entry being shown'), BlockFieldType::Toggle, true),
            new BlockField('pagination', __('Show page links'), BlockFieldType::Toggle, false),
            new BlockField('layout', __('Layout'), BlockFieldType::Select, 'grid', ['grid' => __('Grid'), 'list' => __('List')]),
            new BlockField('columns', __('Columns on big screens'), BlockFieldType::Select, '3', ['2' => '2', '3' => '3', '4' => '4']),
            new BlockField('empty', __('Text when nothing is found'), BlockFieldType::Text, '', max: 200),
        ];
    }

    protected function html(BlockData $block, BlockRenderer $renderer): string
    {
        $render = app(RenderContext::class);
        [$entries, $paginator] = $this->entries($block, $render);

        if ($entries->isEmpty()) {
            $empty = $this->text($block, 'empty');

            return $empty === '' ? '' : '<div class="verso-query"><p class="verso-query__empty">'.e($empty).'</p></div>';
        }

        app(ResponsiveImages::class)->preloadEntries($entries->all());
        $items = '';

        foreach ($entries as $entry) {
            $items .= '<li class="verso-query__item">'.$render->withEntry($entry, fn (): string => $renderer->renderChildren($block)).'</li>';
        }

        $classes = 'verso-query__items verso-query__items--'.$this->choice($block, 'layout', ['grid', 'list'], 'grid')
            .' verso-query__items--columns-'.$this->choice($block, 'columns', ['2', '3', '4'], '3');

        return '<div class="verso-query"><ul class="'.$classes.'" role="list">'.$items.'</ul>'
            .($paginator !== null && $block->bool('pagination') ? $this->pagination($paginator) : '').'</div>';
    }

    /**
     * The entries to draw, and the pages when there are any.
     *
     * @return array{0: Collection<int, Entry>, 1: LengthAwarePaginator<int, Entry>|null}
     */
    protected function entries(BlockData $block, RenderContext $render): array
    {
        $page = $render->data('entries');

        if ($this->choice($block, 'source', ['custom', 'page'], 'custom') === 'page' && $page instanceof LengthAwarePaginator) {
            return [collect($page->items()), $page];
        }

        $query = Entry::query()->published()->ofType($this->text($block, 'type', 'post'))->with('author');
        $term = $this->text($block, 'term');
        $author = $this->text($block, 'author');
        $current = $render->entry() ?? ($render->subject() instanceof Entry ? $render->subject() : null);

        if (ctype_digit($term)) {
            $query->whereIn('id', fn ($pivot) => $pivot->select('entry_id')->from('entry_term')->where('term_id', (int) $term));
        }

        if (ctype_digit($author)) {
            $query->where('author_id', (int) $author);
        }

        if ($current !== null && $block->bool('excludeCurrent', true)) {
            $query->whereKeyNot($current->id);
        }

        match ($this->choice($block, 'order', self::ORDERS, 'date')) {
            'title' => $query->orderBy('title'),
            'updated' => $query->orderByDesc('updated_at'),
            'menu' => $query->orderBy('menu_order'),
            'random' => $query->inRandomOrder(),
            default => $query->orderByDesc('published_at'),
        };
        $query->orderByDesc('id');

        $count = (int) $this->number($block, 'count', 6, 1, (int) config('verso.layouts.query_max'));
        $offset = (int) $this->number($block, 'offset', 0, 0, 100);

        if (! $block->bool('pagination')) {
            return [$query->skip($offset)->take($count)->get(), null];
        }

        $name = 'page-'.substr(preg_replace('/[^a-z0-9]/i', '', $block->id) ?: 'q', 0, 12);
        $paginator = $query->skip($offset)->paginate($count, ['*'], $name)->withQueryString();

        return [collect($paginator->items()), $paginator];
    }

    /** @param LengthAwarePaginator<int, Entry> $paginator */
    protected function pagination(LengthAwarePaginator $paginator): string
    {
        if ($paginator->lastPage() < 2) {
            return '';
        }

        $links = '';

        if ($paginator->currentPage() > 1) {
            $links .= '<li><a href="'.e((string) $paginator->previousPageUrl()).'" rel="prev">'.e(__('Previous page')).'</a></li>';
        }

        foreach (range(1, $paginator->lastPage()) as $number) {
            $links .= '<li><a href="'.e($paginator->url($number)).'"'.($number === $paginator->currentPage() ? ' aria-current="page"' : '').'>'
                .'<span class="verso-visually-hidden">'.e(__('Page')).' </span>'.$number.'</a></li>';
        }

        if ($paginator->hasMorePages()) {
            $links .= '<li><a href="'.e((string) $paginator->nextPageUrl()).'" rel="next">'.e(__('Next page')).'</a></li>';
        }

        return '<nav class="verso-query__pages" aria-label="'.e(__('Pages')).'"><ul>'.$links.'</ul></nav>';
    }
}
