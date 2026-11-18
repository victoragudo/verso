<?php

namespace App\Verso\Layouts;

use App\Models\User;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\I18n\Translate;
use App\Verso\Layouts\Conditions\ConditionRegistry;
use App\Verso\Layouts\Conditions\PageContext;
use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Theme\Http\Controllers\ArchiveController;
use App\Verso\Theme\Http\Controllers\HomeController;
use App\Verso\Theme\Http\Controllers\SearchController;
use App\Verso\Theme\Http\Controllers\TermController;
use App\Verso\Theme\Support\FrontPage;
use App\Verso\Theme\Support\ThemeResponse;
use App\Verso\Theme\ThemeManager;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

// The pages a layout can be previewed on, and the drawing of one of them.
class LayoutPreview
{
    // Sample entries listed per content type.
    public const int SAMPLES = 3;

    public function __construct(
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected ThemeManager $themes,
        protected ConditionRegistry $conditions,
        protected FrontPage $frontPage,
    ) {}

    /**
     * The pages to preview on. usesLayout says if the rules of the layout pick that page for a visitor or for the viewer.
     *
     * @return list<array{value: string, label: string, usesLayout: bool}>
     */
    public function targets(SiteLayout $layout, ?User $viewer = null): array
    {
        $frontPage = $this->frontPage->entry();
        $home = $frontPage === null
            ? new PageContext(kind: 'home')
            : $this->entryPage($frontPage);
        $targets = [$this->target($layout, $viewer, 'home', Translate::text('Home page'), $home)];
        $listed = [];

        foreach ($this->types->all() as $type) {
            $entries = Entry::query()->ofType($type->name)->published()->with(['terms', 'author'])->latest('published_at')->limit(self::SAMPLES)->get();

            foreach ($entries as $entry) {
                $listed[] = $entry->id;
                $targets[] = $this->target($layout, $viewer, 'entry:'.$entry->id, $entry->title.' ('.Translate::text($type->label).')', $this->entryPage($entry));
            }

            if ($type->hasArchive) {
                $targets[] = $this->target($layout, $viewer, 'archive:'.$type->name, Translate::text(':type archive', ['type' => Translate::text($type->pluralLabel)]), new PageContext(kind: 'archive', contentType: $type));
            }
        }

        foreach ($this->ruledEntries($layout, $listed) as $entry) {
            $targets[] = $this->target($layout, $viewer, 'entry:'.$entry->id, $entry->title.' ('.Translate::text($this->types->find($entry->type)->label ?? $entry->type).')', $this->entryPage($entry));
        }

        $term = $this->ruledTerm($layout) ?? Term::query()->orderBy('id')->first();

        if ($term !== null) {
            $targets[] = $this->target($layout, $viewer, 'term:'.$term->id, $term->name, new PageContext(kind: 'taxonomy', term: $term));
        }

        $targets[] = $this->target($layout, $viewer, 'search', Translate::text('Search results'), new PageContext(kind: 'search'));
        $targets[] = $this->target($layout, $viewer, '404', Translate::text('Page not found'), new PageContext(kind: '404'));

        return $targets;
    }

    /** @return array{value: string, label: string, usesLayout: bool} */
    protected function target(SiteLayout $layout, ?User $viewer, string $value, string $label, PageContext $page): array
    {
        return ['value' => $value, 'label' => $label, 'usesLayout' => $this->uses($layout, $page, $viewer)];
    }

    // True when the rules pick the page for a visitor who is not signed in, or for the person looking at the preview.
    protected function uses(SiteLayout $layout, PageContext $page, ?User $viewer): bool
    {
        $rules = (array) $layout->conditions;

        foreach ([null, $viewer] as $person) {
            $context = new PageContext($page->kind, $page->entry, $page->term, $page->contentType, $page->author, $person);

            if ($this->conditions->score($rules, $context) !== null) {
                return true;
            }
        }

        return false;
    }

    protected function entryPage(Entry $entry): PageContext
    {
        return new PageContext(kind: $entry->isFrontPage() ? 'front-page' : 'entry', entry: $entry, author: $entry->author);
    }

    /**
     * Published entries named by "one entry" rules that are not listed yet, so the preview can open on them.
     *
     * @param  list<int>  $listed
     * @return Collection<int, Entry>
     */
    protected function ruledEntries(SiteLayout $layout, array $listed): Collection
    {
        $ids = array_diff($this->ruleValues($layout, 'entry'), $listed);

        return $ids === [] ? new Collection : Entry::query()->published()->with(['terms', 'author'])->whereIn('id', $ids)->limit(self::SAMPLES)->get();
    }

    protected function ruledTerm(SiteLayout $layout): ?Term
    {
        $ids = $this->ruleValues($layout, 'term');

        return $ids === [] ? null : Term::query()->whereIn('id', $ids)->orderBy('id')->first();
    }

    /** @return list<int> */
    protected function ruleValues(SiteLayout $layout, string $type): array
    {
        $ids = [];

        foreach ((array) $layout->conditions as $rule) {
            if (is_array($rule) && ($rule['type'] ?? null) === $type && ($rule['exclude'] ?? false) !== true && is_numeric($rule['value'] ?? null)) {
                $ids[] = (int) $rule['value'];
            }
        }

        return array_values(array_unique($ids));
    }

    // Draws one target page. Unknown targets show the home page.
    public function render(string $target): Response
    {
        [$kind, $value] = array_pad(explode(':', $target, 2), 2, '');

        // Admin requests only know the fallback theme views, so point "theme::" at the active theme.
        $this->themes->registerViews();

        return match ($kind) {
            'entry' => app(ThemeResponse::class)->entry(Entry::query()->findOrFail((int) $value)),
            'archive' => app()->call([app(ArchiveController::class), '__invoke'], ['contentType' => $this->types->get($value)]),
            'term' => $this->term((int) $value),
            'search' => app()->call([app(SearchController::class), '__invoke']),
            '404' => app(ThemeResponse::class)->notFound(),
            default => app()->call([app(HomeController::class), '__invoke']),
        };
    }

    protected function term(int $id): Response
    {
        $term = Term::query()->findOrFail($id);

        return app()->call([app(TermController::class), '__invoke'], ['taxonomy' => $this->taxonomies->get($term->taxonomy), 'term' => $term]);
    }
}
