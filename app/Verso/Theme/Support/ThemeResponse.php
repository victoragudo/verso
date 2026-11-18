<?php

namespace App\Verso\Theme\Support;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Editor\Support\RenderContext;
use App\Verso\Media\ResponsiveImages;
use App\Verso\Media\View\Components\FeaturedImage;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Site\Seo\SeoBuilder;
use App\Verso\Theme\AdminBar\AdminBarContext;
use App\Verso\Theme\Templates\TemplateCandidates;
use App\Verso\Theme\Templates\TemplateContext;
use App\Verso\Theme\Templates\TemplateHierarchy;
use App\Verso\Theme\Templates\TemplateOverrides;
use App\Verso\Theme\Templates\TemplatePartChoices;
use App\Verso\Theme\Templates\TemplateResolver;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Response;
use InvalidArgumentException;

// Builds HTTP responses from the views of the active theme, following the template hierarchy.
class ThemeResponse
{
    public function __construct(
        protected ViewFactory $views,
        protected ContentRenderer $content,
        protected ResponsiveImages $images,
        protected LeadImage $leadImage,
        protected TemplateHierarchy $hierarchy,
        protected TemplateResolver $resolver,
        protected AdminBarContext $adminBar,
        protected TemplatePartChoices $partChoices,
    ) {}

    /**
     * Render one fixed view, like "shop.cart".
     *
     * @param  array<string, mixed>  $data
     */
    public function view(string $view, array $data = [], int $status = 200): Response
    {
        return $this->render(TemplateCandidates::single(TemplateContext::Custom, $view), $data, $status);
    }

    /**
     * Render the first candidate view that exists. Throws when the theme has none of them.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(TemplateCandidates $candidates, array $data = [], int $status = 200): Response
    {
        // Dynamic blocks and layout conditions read what the page is about.
        app(RenderContext::class)->setPage($candidates->context->value, $candidates->subject, $data);
        $override = app(TemplateOverrides::class)->render($candidates, $data);

        if ($override !== null) {
            return new Response($override, $status);
        }

        $chosen = $this->resolver->resolve($candidates)->chosen;
        $name = $this->resolver->viewName((string) $chosen);

        if ($chosen === null || ! $this->views->exists($name)) {
            throw new InvalidArgumentException('The theme has no ['.implode(', ', $candidates->names).'] view.');
        }

        return new Response($this->views->make($name, $data)->render(), $status);
    }

    // Show one entry with the most specific view: page template, front-page, entry-{type}-{slug} and so on.
    public function entry(Entry $entry): Response
    {
        $entry->loadMissing(['author', 'terms']);
        $this->adminBar->viewing($entry);
        $this->images->preloadEntries([$entry]);
        // The header and footer styles of the entry or its page template, like a minimal header.
        $this->partChoices->apply($entry);

        $content = $this->content->render($entry);

        // Without a featured image on top, the first image of the content is the lead image.
        if (! $this->showsFeaturedImage($entry)) {
            $content = $this->leadImage->promote($content);
        }

        try {
            return $this->render($this->hierarchy->forEntry($entry), [
                'entry' => $entry,
                'content' => $content,
            ]);
        } finally {
            // The styles belong to this entry only, not to later pages of the same process.
            $this->partChoices->reset();
        }
    }

    // True when the entry page prints a featured image above the content.
    public function showsFeaturedImage(Entry $entry): bool
    {
        return (new FeaturedImage($this->images, $entry, skipLeadImage: true))->shouldRender();
    }

    /**
     * Entries of one term, like "/category/travel".
     *
     * @param  array<string, mixed>  $data
     */
    public function term(Taxonomy $taxonomy, Term $term, array $data): Response
    {
        return $this->render($this->hierarchy->forTerm($taxonomy, $term), ['taxonomy' => $taxonomy, 'term' => $term, ...$data]);
    }

    /**
     * Entries of one content type, like "/blog".
     *
     * @param  array<string, mixed>  $data
     */
    public function archive(ContentType $contentType, array $data): Response
    {
        return $this->render($this->hierarchy->forArchive($contentType), ['contentType' => $contentType, ...$data]);
    }

    /**
     * The posts of one author, like "/author/clara-moreno".
     *
     * @param  array<string, mixed>  $data
     */
    public function author(User $author, array $data): Response
    {
        // The SEO tags print a ProfilePage about this person.
        request()->attributes->set(SeoBuilder::AUTHOR_ATTRIBUTE, $author);

        return $this->render($this->hierarchy->forAuthor($author), ['author' => $author, ...$data]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function home(array $data): Response
    {
        return $this->render($this->hierarchy->home(), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function search(array $data): Response
    {
        return $this->render($this->hierarchy->search(), $data);
    }

    public function notFound(): Response
    {
        return $this->render($this->hierarchy->notFound(), [], 404);
    }

    // The most specific entry view that exists for a content type, without slug or id views.
    public function entryView(string $type): string
    {
        return $this->views->exists($this->resolver->viewName('entry-'.$type)) ? 'entry-'.$type : 'entry';
    }

    // The most specific term view without slug views: a theme or plugin can add "taxonomy-<name>" (like "taxonomy-product_category").
    public function taxonomyView(string $taxonomy): string
    {
        return $this->views->exists($this->resolver->viewName('taxonomy-'.$taxonomy)) ? 'taxonomy-'.$taxonomy : 'taxonomy';
    }
}
