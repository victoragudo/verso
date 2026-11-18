<?php

namespace App\Verso\Site\Parts;

use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Theme\Templates\PageTemplates;
use App\Verso\Theme\Templates\PartVariants;
use App\Verso\Theme\Templates\TemplateCandidates;
use App\Verso\Theme\Templates\TemplateContext;
use App\Verso\Theme\Templates\TemplateHierarchy;
use App\Verso\Theme\Templates\TemplatePartChoices;
use App\Verso\Theme\Templates\TemplateResolver;
use App\Verso\Theme\Theme;

// The Templates view: which theme file each kind of page uses, in plain words, and the header and footer styles it gets.
class SiteTemplates
{
    public function __construct(
        protected TemplateHierarchy $hierarchy,
        protected TemplateResolver $resolver,
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected PageTemplates $pageTemplates,
        protected PartVariants $variants,
        protected TemplatePartChoices $partChoices,
        protected ContentLabels $labels,
    ) {}

    /**
     * @return array{rows: list<array{label: string, kind: string, template: string|null, tried: list<string>}>, parts: list<array{name: string, label: string, style: string}>, templateParts: array{parts: list<array<string, mixed>>, templates: list<array<string, mixed>>}}
     */
    public function overview(Theme $theme): array
    {
        $rows = [
            $this->row(__('Home page with the latest posts'), 'home', $this->hierarchy->home()),
            $this->row(__('Search results'), 'search', $this->hierarchy->search()),
            $this->row(__('Page not found'), '404', $this->hierarchy->notFound()),
        ];

        foreach ($this->types->all() as $type) {
            $rows[] = $this->row(__('One :type', ['type' => $this->labels->typeLabel($type)]), 'entry', new TemplateCandidates(TemplateContext::Entry, ["entry-{$type->name}", 'entry']));

            if ($type->hasArchive) {
                $rows[] = $this->row(__('List of :types', ['types' => $this->labels->typePluralLabel($type)]), 'archive', $this->hierarchy->forArchive($type));
            }
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            $rows[] = $this->row(__('One :taxonomy', ['taxonomy' => $this->labels->taxonomyLabel($taxonomy)]), 'taxonomy', new TemplateCandidates(TemplateContext::Taxonomy, ["taxonomy-{$taxonomy->name}", 'taxonomy', 'archive']));
        }

        foreach ($this->pageTemplates->all($theme) as $template) {
            $type = $template->types[0] ?? 'page';
            $rows[] = $this->row(__('Page template: :template', ['template' => $template->label]), 'custom', new TemplateCandidates(TemplateContext::Custom, [$template->view, "entry-{$type}", 'entry']));
        }

        $parts = [];

        foreach ($this->variants->definitions($theme) as $name => $definition) {
            $chosen = (string) $this->variants->chosen($theme, $name);
            $parts[] = ['name' => $name, 'label' => $definition['label'], 'style' => $definition['variants'][$chosen]['label']];
        }

        return ['rows' => $rows, 'parts' => $parts, 'templateParts' => $this->templateParts($theme)];
    }

    /**
     * Header and footer styles per page template: what theme.json asks for and what was saved here.
     *
     * @return array{parts: list<array<string, mixed>>, templates: list<array<string, mixed>>}
     */
    public function templateParts(Theme $theme): array
    {
        $parts = $this->partChoices->options($theme);

        if ($parts === []) {
            return ['parts' => [], 'templates' => []];
        }

        $saved = $this->partChoices->saved($theme);
        $templates = [];

        foreach ($this->pageTemplates->all($theme) as $template) {
            $templates[] = [
                'name' => $template->name,
                'label' => $template->label,
                'themeParts' => (object) $template->parts,
                'saved' => (object) ($saved[$template->name] ?? []),
                'styles' => (object) $this->partChoices->forTemplate($theme, $template),
            ];
        }

        return ['parts' => $parts, 'templates' => $templates];
    }

    /**
     * @return array{label: string, kind: string, template: string|null, tried: list<string>}
     */
    protected function row(string $label, string $kind, TemplateCandidates $candidates): array
    {
        $lookup = $this->resolver->resolve($candidates);

        return [
            'label' => $label,
            'kind' => $kind,
            'template' => $lookup->chosen,
            'tried' => array_values(array_map(fn (array $candidate): string => $candidate['name'], array_filter($lookup->candidates, fn (array $candidate): bool => $candidate['checked']))),
        ];
    }
}
