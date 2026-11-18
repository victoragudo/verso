<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Taxonomy;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Site\Actions\UpdatePermalinks;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Http\Requests\PermalinksRequest;
use App\Verso\Site\Http\Requests\SearchEngineSettingsRequest;
use App\Verso\Site\Http\Requests\SitemapSettingsRequest;
use App\Verso\Site\Permalinks\PermalinkPattern;
use App\Verso\Site\Permalinks\Permalinks;
use App\Verso\Site\Permalinks\PermalinkSettings;
use App\Verso\Site\Seo\SeoSettings;
use App\Verso\Site\Sitemap\SitemapBuilder;
use App\Verso\Site\Sitemap\SitemapSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// Admin page "Permalinks and SEO": URL structures, sitemap options and search engine settings.
class PermalinksSeoController extends Controller
{
    public const TABS = ['permalinks', 'sitemap', 'search'];

    public function __construct(
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected Permalinks $permalinks,
        protected PermalinkSettings $permalinkSettings,
        protected SitemapSettings $sitemap,
        protected SeoSettings $seo,
        protected Settings $settings,
        protected ContentLabels $labels,
    ) {}

    public function edit(SitemapBuilder $builder): Response
    {
        Gate::authorize(Capability::ManagePermalinks->value);

        return Inertia::render('admin/site/permalinks', [
            'permalinks' => $this->permalinkValues(),
            'types' => array_values(array_map($this->typeData(...), $this->types->all())),
            'taxonomies' => array_values(array_map(fn (Taxonomy $taxonomy): array => [
                'name' => $taxonomy->name,
                'label' => $this->labels->taxonomyLabel($taxonomy),
                'defaultBase' => trim($taxonomy->routeBase, '/'),
                'sample' => 'travel',
            ], $this->taxonomies->all())),
            'presets' => (array) config('verso.permalinks.presets'),
            'tokens' => array_keys(PermalinkPattern::TOKENS),
            'trailingSlashNotice' => file_exists(public_path('.htaccess')) && str_contains((string) file_get_contents(public_path('.htaccess')), 'Redirect Trailing Slashes'),
            'sitemap' => $this->sitemapValues(),
            'sitemapStatus' => $this->sitemap->enabled() ? $builder->counts() : [],
            'entryOptions' => $this->entryOptions(),
            'search' => $this->searchValues(),
            'links' => [
                'sitemap' => url('/sitemap.xml'),
                'robots' => url('/robots.txt'),
                'site' => url('/'),
            ],
            'robotsDefault' => (string) config('verso.seo.robots_default'),
            'site' => [
                'name' => (string) $this->settings->get('site.name'),
                'tagline' => (string) $this->settings->get('site.tagline'),
            ],
        ]);
    }

    public function updatePermalinks(PermalinksRequest $request, UpdatePermalinks $update): RedirectResponse
    {
        $update->handle($request->values());

        return $this->saved(__('URL structure saved. Old links redirect to the new ones.'), 'permalinks');
    }

    public function updateSitemap(SitemapSettingsRequest $request): RedirectResponse
    {
        return $this->save($request->settings(), __('Sitemap settings saved.'), 'sitemap');
    }

    public function updateSearch(SearchEngineSettingsRequest $request): RedirectResponse
    {
        return $this->save($request->settings(), __('Search engine settings saved.'), 'search');
    }

    /** @param  array<string, mixed>  $values */
    protected function save(array $values, string $message, string $tab): RedirectResponse
    {
        // Saving settings empties the page cache. The event refreshes the sitemap and menus.
        $this->settings->setMany($values);
        SiteSettingsUpdated::dispatch(array_keys($values));

        return $this->saved($message, $tab);
    }

    protected function saved(string $message, string $tab): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.site.permalinks.edit', ['tab' => $tab]);
    }

    /** @return array<string, mixed> */
    protected function permalinkValues(): array
    {
        $structures = [];
        $archiveBases = [];
        $taxonomyBases = [];

        foreach ($this->types->all() as $type) {
            $structures[$type->name] = $this->permalinkSettings->pattern($type)->pattern;

            if ($type->hasArchive) {
                $archiveBases[$type->name] = $this->permalinkSettings->archiveBase($type);
            }
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            $taxonomyBases[$taxonomy->name] = $this->permalinkSettings->taxonomyBase($taxonomy);
        }

        return [
            'structures' => $structures,
            'archive_bases' => (object) $archiveBases,
            'taxonomy_bases' => $taxonomyBases,
            'trailing_slash' => $this->permalinkSettings->trailingSlash(),
            'lowercase' => $this->permalinkSettings->lowercase(),
        ];
    }

    // A type with its defaults and the token values of its newest entry, for the live preview.
    /** @return array<string, mixed> */
    protected function typeData(ContentType $type): array
    {
        $entry = Entry::query()->ofType($type->name)->published()->with('parent')->latest('published_at')->latest('id')->first();

        return [
            'name' => $type->name,
            'label' => $this->labels->typeLabel($type),
            'pluralLabel' => $this->labels->typePluralLabel($type),
            'inlineLabel' => $this->labels->inline($this->labels->typeLabel($type)),
            'inlinePluralLabel' => $this->labels->inline($this->labels->typePluralLabel($type)),
            'hierarchical' => $type->hierarchical,
            'hasArchive' => $type->hasArchive,
            'defaultStructure' => PermalinkSettings::defaultPattern($type),
            'defaultArchiveBase' => trim($type->routeBase, '/'),
            'previous' => array_map(fn (PermalinkPattern $pattern): string => $pattern->pattern, $this->permalinkSettings->previousPatterns($type)),
            'sample' => $entry === null ? $this->sampleValues($type) : $this->permalinks->values($entry, true),
        ];
    }

    /** @return array<string, string> */
    protected function sampleValues(ContentType $type): array
    {
        $now = now();

        return [
            'slug' => $type->hierarchical ? 'about/team' : 'sample-'.$type->name,
            'id' => '42',
            'year' => $now->format('Y'),
            'month' => $now->format('m'),
            'day' => $now->format('d'),
            'category' => (string) config('verso.permalinks.uncategorized'),
        ];
    }

    /** @return array<string, mixed> */
    protected function sitemapValues(): array
    {
        return [
            'enabled' => $this->sitemap->enabled(),
            'types' => $this->sitemap->types() ?? array_values(array_diff($this->types->names(), (array) config('verso.sitemap.exclude_types'))),
            'taxonomies' => $this->sitemap->taxonomies() ?? array_values(array_diff(array_keys($this->taxonomies->all()), (array) config('verso.sitemap.exclude_taxonomies'))),
            'excluded_entries' => $this->sitemap->excludedEntries(),
            'include_images' => $this->sitemap->includeImages(),
            'max_urls' => $this->sitemap->maxUrls(),
            'last_modified' => $this->sitemap->lastModified(),
        ];
    }

    // Published entries for the "Leave out" picker: the newest ones plus those already left out.
    /** @return list<array{value: string, label: string, group: string}> */
    protected function entryOptions(): array
    {
        $ids = array_values(array_unique([...$this->latestEntryIds(), ...$this->sitemap->excludedEntries()]));

        return array_values(Entry::query()
            ->published()
            ->whereIn('id', $ids)
            ->orderBy('title')
            ->orderBy('id')
            ->get(['id', 'title', 'type'])
            ->map(fn (Entry $entry): array => [
                'value' => (string) $entry->id,
                'label' => $entry->title,
                'group' => $this->groupLabel($entry->type),
            ])
            ->all());
    }

    protected function groupLabel(string $type): string
    {
        $contentType = $this->types->find($type);

        return $contentType === null ? $type : $this->labels->typePluralLabel($contentType);
    }

    /** @return list<int> */
    protected function latestEntryIds(): array
    {
        return array_values(Entry::query()->published()->latest('published_at')->latest('id')
            ->limit((int) config('verso.sitemap.entry_picker_limit'))
            ->pluck('id')->map(intval(...))->all());
    }

    /** @return array<string, mixed> */
    protected function searchValues(): array
    {
        return [
            'discourage' => $this->seo->discourage(),
            'robots_txt' => $this->seo->robotsTxt(),
            'title_format' => $this->seo->titleFormat(),
            'title_separator' => $this->seo->separatorSymbol(),
            'default_description' => (string) $this->seo->defaultDescription(),
            'default_image' => $this->media($this->seo->defaultImageId()),
            'schema_type' => $this->seo->schemaType(),
            'schema_name' => $this->seo->schemaName(),
            'schema_logo' => $this->media($this->seo->schemaLogoId()),
            'schema_same_as' => $this->seo->sameAs(),
            'verification_google' => (string) ($this->seo->verificationTags()['google-site-verification'] ?? ''),
            'verification_bing' => (string) ($this->seo->verificationTags()['msvalidate.01'] ?? ''),
            'indexnow_enabled' => (bool) $this->settings->get(SeoSettings::INDEXNOW_ENABLED),
            'indexnow_key' => (string) $this->settings->get(SeoSettings::INDEXNOW_KEY),
        ];
    }

    /** @return array{id: int, url: string, filename: string}|null */
    protected function media(?int $id): ?array
    {
        $media = $id === null ? null : Media::query()->find($id);

        return $media instanceof Media ? ['id' => $media->id, 'url' => $media->url(), 'filename' => $media->filename] : null;
    }
}
