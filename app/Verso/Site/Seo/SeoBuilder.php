<?php

namespace App\Verso\Site\Seo;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\SiteTime;
use App\Verso\Plugins\Filters\SeoMetaFilter;
use App\Verso\Site\Permalinks\Permalinks;
use Illuminate\Support\Str;

// Builds the SEO data (meta, Open Graph, Twitter and JSON-LD) of a page.
class SeoBuilder
{
    public const SEARCH_TERM = 'search_term_string';

    // Request attribute with the User shown on an author page.
    public const AUTHOR_ATTRIBUTE = 'verso.author';

    // Google shows at most 110 characters of an Article headline.
    protected const HEADLINE_MAX_LENGTH = 110;

    public function __construct(
        protected Settings $settings,
        protected SiteTime $time,
        protected SeoSettings $seo,
        protected FaqSchema $faq,
    ) {}

    // SEO data for an entry page, or for a general page (home, archive) when $entry is null. Plugins can change it.
    public function build(?Entry $entry = null, ?string $title = null, ?string $description = null): SeoPage
    {
        $seo = $entry === null
            ? $this->forGeneralPage($title, $description)
            : $this->forEntry($entry, $title, $description);

        return SeoMetaFilter::apply($seo, $entry);
    }

    public function forEntry(Entry $entry, ?string $title = null, ?string $description = null): SeoPage
    {
        $seo = EntrySeo::fromEntry($entry);
        $url = $entry->url();
        $pageTitle = $seo->title ?? $title ?? $this->withSiteName($entry->title);
        $pageDescription = $seo->description ?? $this->shorten($description ?? $entry->excerpt) ?? $this->defaultDescription();
        $canonical = $this->absolute($seo->canonical) ?? $url;
        $image = $this->absolute($seo->ogImage) ?? $this->seo->defaultImageUrl();
        $isArticle = $this->isArticle($entry);

        $shareTitle = $seo->ogTitle ?? $pageTitle;
        $shareDescription = $seo->ogDescription ?? $pageDescription;
        $openGraph = $this->openGraph($shareTitle, $shareDescription, $canonical, $image, $isArticle ? 'article' : 'website');

        if ($isArticle) {
            $openGraph += array_filter([
                'article:published_time' => $this->time->iso($entry->published_at),
                'article:modified_time' => $this->time->iso($entry->updated_at),
                'article:author' => $entry->author?->name,
            ]);
        }

        $schema = [...$this->siteNodes(), $this->entryNode($entry, $pageTitle, $pageDescription, $canonical, $image)];

        if (! $entry->isFrontPage()) {
            $schema[] = $this->breadcrumbs($entry);
        }

        $faq = $this->faq->node(is_array($entry->blocks) ? $entry->blocks : [], $canonical);

        if ($faq !== null) {
            $schema[] = $faq;
        }

        return new SeoPage(
            title: $pageTitle,
            description: $pageDescription,
            canonical: $canonical,
            robots: $this->seo->discourage() ? $this->robots(false) : $seo->robots(),
            openGraph: $openGraph,
            twitter: $this->twitter($shareTitle, $shareDescription, $image),
            schema: $schema,
        );
    }

    public function forGeneralPage(?string $title = null, ?string $description = null): SeoPage
    {
        $tagline = $this->tagline();
        $pageTitle = $title !== null ? $this->withSiteName($title) : $this->withSeparator($this->siteName(), $tagline);
        $pageDescription = $this->shorten($description) ?? $this->defaultDescription();
        $canonical = url()->current();
        $image = $this->seo->defaultImageUrl();
        $author = request()->attributes->get(self::AUTHOR_ATTRIBUTE);
        $schema = $this->siteNodes();

        if ($author instanceof User) {
            $schema[] = $this->profilePage($author, $canonical, $pageTitle);
        }

        return new SeoPage(
            title: $pageTitle,
            description: $pageDescription,
            canonical: $canonical,
            robots: $this->robots(false),
            openGraph: $this->openGraph($pageTitle, $pageDescription, $canonical, $image, $author instanceof User ? 'profile' : 'website'),
            twitter: $this->twitter($pageTitle, $pageDescription, $image),
            schema: $schema,
        );
    }

    // The Person node of an author, linked to their public author page and social profiles.
    /** @return array<string, mixed> */
    public function person(User $author): array
    {
        $url = $author->authorUrl();
        $sameAs = array_column($author->socialProfiles(), 'url');
        $avatar = $author->relationLoaded('avatar') ? $author->avatar : null;
        $bio = trim(strip_tags((string) $author->bio));

        return array_filter([
            '@type' => 'Person',
            '@id' => $url.'#person',
            'name' => $author->name,
            'url' => $url,
            'description' => $bio === '' ? null : Str::limit($bio, (int) config('verso.seo.description_max_length')),
            'image' => $avatar === null ? null : url($avatar->url()),
            'sameAs' => $sameAs === [] ? null : $sameAs,
        ], fn (mixed $value): bool => $value !== null);
    }

    // An author page: a ProfilePage about the Person.
    /** @return array<string, mixed> */
    protected function profilePage(User $author, string $url, string $title): array
    {
        return [
            '@type' => 'ProfilePage',
            '@id' => $url.'#profilepage',
            'url' => $url,
            'name' => $title,
            'inLanguage' => $this->locale(),
            'isPartOf' => ['@id' => $this->siteUrl().'/#website'],
            'mainEntity' => $this->person($author),
        ];
    }

    // The schema.org type of an article entry, like BlogPosting for posts.
    public function articleType(Entry $entry): string
    {
        $types = (array) config('verso.seo.schema_types');
        $type = $types[$entry->type] ?? null;

        return is_string($type) && $type !== '' ? $type : 'Article';
    }

    // The WebSite node, plus the Organization or Person behind the site when it is set.
    /** @return list<array<string, mixed>> */
    public function siteNodes(): array
    {
        $publisher = $this->seo->publisher($this->siteUrl());

        return $publisher === null ? [$this->webSite()] : [$this->webSite(), $publisher];
    }

    // The WebSite node with a SearchAction so search engines can show a search box.
    /** @return array<string, mixed> */
    public function webSite(): array
    {
        $searchUrl = $this->siteUrl().'/'.ltrim((string) config('verso.seo.search_path'), '/')
            .'?'.config('verso.seo.search_param').'={'.self::SEARCH_TERM.'}';

        return array_filter([
            '@type' => 'WebSite',
            '@id' => $this->siteUrl().'/#website',
            'url' => $this->siteUrl().'/',
            'name' => $this->siteName(),
            'description' => $this->tagline(),
            'inLanguage' => $this->locale(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $searchUrl],
                'query-input' => 'required name='.self::SEARCH_TERM,
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    // Home, then the archive of the type (like "Blog"), then the parents, then the entry.
    /** @return array<string, mixed> */
    public function breadcrumbs(Entry $entry): array
    {
        $crumbs = [['name' => $this->siteName(), 'url' => $this->siteUrl().'/']];
        $type = $entry->contentType();

        $archive = app(Permalinks::class)->archiveUrl($type->name);

        if ($archive !== null) {
            $crumbs[] = ['name' => $type->pluralLabel, 'url' => $archive];
        }

        foreach ($entry->ancestors() as $ancestor) {
            $crumbs[] = ['name' => $ancestor->title, 'url' => $ancestor->url()];
        }

        $crumbs[] = ['name' => $entry->title, 'url' => $entry->url()];

        $items = [];
        foreach ($crumbs as $index => $crumb) {
            $items[] = ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb['name'], 'item' => $crumb['url']];
        }

        return ['@type' => 'BreadcrumbList', '@id' => $entry->url().'#breadcrumb', 'itemListElement' => $items];
    }

    public function isArticle(Entry $entry): bool
    {
        return in_array($entry->type, (array) config('verso.seo.article_types'), true);
    }

    // An Article for posts, a WebPage for everything else.
    /** @return array<string, mixed> */
    protected function entryNode(Entry $entry, string $title, ?string $description, string $canonical, ?string $image): array
    {
        $common = [
            'url' => $canonical,
            'description' => $description,
            'image' => $image,
            'inLanguage' => $this->locale(),
            'datePublished' => $this->time->iso($entry->published_at),
            'dateModified' => $this->time->iso($entry->updated_at),
        ];

        if (! $this->isArticle($entry)) {
            return array_filter([
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'name' => $title,
                ...$common,
                'isPartOf' => ['@id' => $this->siteUrl().'/#website'],
            ], fn (mixed $value): bool => $value !== null);
        }

        return array_filter([
            '@type' => $this->articleType($entry),
            '@id' => $canonical.'#article',
            'headline' => Str::limit($entry->title, self::HEADLINE_MAX_LENGTH, ''),
            ...$common,
            'mainEntityOfPage' => $canonical,
            'author' => $entry->author === null ? null : $this->person($entry->author),
            'publisher' => $this->seo->publisher($this->siteUrl()) === null
                ? ['@type' => 'Organization', 'name' => $this->siteName(), 'url' => $this->siteUrl().'/']
                : ['@id' => $this->siteUrl().'/#'.$this->seo->schemaType()],
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> */
    protected function openGraph(string $title, ?string $description, string $url, ?string $image, string $type): array
    {
        return array_filter([
            'og:type' => $type,
            'og:site_name' => $this->siteName(),
            'og:locale' => $this->locale(),
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $url,
            'og:image' => $image,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    /** @return array<string, string> */
    protected function twitter(string $title, ?string $description, ?string $image): array
    {
        return array_filter([
            'twitter:card' => $image === null ? 'summary' : 'summary_large_image',
            'twitter:site' => (string) config('verso.seo.twitter_site'),
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $image,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    // The whole site can be hidden from search engines in the settings.
    protected function robots(bool $noindex): ?string
    {
        if ($this->seo->discourage()) {
            return 'noindex, nofollow';
        }

        return $noindex ? 'noindex, follow' : null;
    }

    // The page title from the title format setting.
    protected function withSiteName(string $title): string
    {
        return $this->seo->title($title, $this->siteName(), $this->tagline());
    }

    // The default meta description from the settings, else the tagline.
    protected function defaultDescription(): ?string
    {
        return $this->shorten($this->seo->defaultDescription()) ?? $this->tagline();
    }

    protected function withSeparator(string $first, ?string $second): string
    {
        if ($second === null || $second === '') {
            return $first;
        }

        return $first.$this->seo->separator().$second;
    }

    protected function shorten(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        return $text === '' ? null : Str::limit($text, (int) config('verso.seo.description_max_length'));
    }

    // Local paths like "/img/cover.jpg" become full URLs.
    protected function absolute(?string $link): ?string
    {
        if ($link === null) {
            return null;
        }

        return str_starts_with($link, '/') ? url($link) : $link;
    }

    protected function siteName(): string
    {
        return (string) $this->settings->get('site.name');
    }

    protected function siteUrl(): string
    {
        return rtrim((string) $this->settings->get('site.url'), '/');
    }

    protected function tagline(): ?string
    {
        $tagline = trim((string) $this->settings->get('site.tagline'));

        return $tagline === '' ? null : $tagline;
    }

    protected function locale(): string
    {
        return (string) $this->settings->get('site.locale');
    }
}
