<?php

namespace App\Verso\Theme\Templates;

use App\Models\User;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Taxonomy;
use App\Verso\Plugins\Filters\TemplateCandidatesFilter;

// Builds the ordered list of theme views to try for each kind of public page (see docs/THEMES.md).
class TemplateHierarchy
{
    public function __construct(protected PageTemplates $pageTemplates) {}

    // A chosen page template, then front-page (for the front page), then entry-{type}-{slug}, entry-{type}-{id}, entry-{type}, entry.
    public function forEntry(Entry $entry): TemplateCandidates
    {
        $names = [];
        $frontPage = $entry->isFrontPage();
        $template = $this->pageTemplates->forEntry($entry);

        if ($template !== null) {
            $names[] = $template->view;
        }

        if ($frontPage) {
            $names[] = 'front-page';
        }

        array_push(
            $names,
            "entry-{$entry->type}-{$entry->slug}",
            "entry-{$entry->type}-{$entry->id}",
            "entry-{$entry->type}",
            'entry',
        );

        return $this->build($frontPage ? TemplateContext::FrontPage : TemplateContext::Entry, $names, $entry);
    }

    // taxonomy-{taxonomy}-{slug}, taxonomy-{taxonomy}, taxonomy, archive.
    public function forTerm(Taxonomy $taxonomy, Term $term): TemplateCandidates
    {
        return $this->build(TemplateContext::Taxonomy, [
            "taxonomy-{$taxonomy->name}-{$term->slug}",
            "taxonomy-{$taxonomy->name}",
            'taxonomy',
            'archive',
        ], $term);
    }

    // archive-{type}, archive.
    public function forArchive(ContentType $type): TemplateCandidates
    {
        return $this->build(TemplateContext::Archive, ["archive-{$type->name}", 'archive'], $type);
    }

    // author-{slug}, author, archive.
    public function forAuthor(User $author): TemplateCandidates
    {
        return $this->build(TemplateContext::Author, ["author-{$author->slug}", 'author', 'archive'], $author);
    }

    // The latest entries on "/" when no front page is chosen.
    public function home(): TemplateCandidates
    {
        return $this->build(TemplateContext::Home, ['home']);
    }

    public function search(): TemplateCandidates
    {
        return $this->build(TemplateContext::Search, ['search']);
    }

    public function notFound(): TemplateCandidates
    {
        return $this->build(TemplateContext::NotFound, ['404']);
    }

    /**
     * Let plugins change the list, then drop unsafe names.
     *
     * @param  list<string>  $names
     */
    protected function build(TemplateContext $context, array $names, ?object $subject = null): TemplateCandidates
    {
        return new TemplateCandidates($context, TemplateCandidatesFilter::apply($names, $context, $subject), $subject);
    }
}
