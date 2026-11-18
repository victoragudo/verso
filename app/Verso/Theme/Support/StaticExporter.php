<?php

namespace App\Verso\Theme\Support;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\Site\Permalinks\Permalinks;
use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

// Renders every public page and writes it as a static file, ready for any web server.
class StaticExporter
{
    /** @var array<string, string> */
    protected array $written = [];

    public function __construct(
        protected Kernel $kernel,
        protected Filesystem $files,
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected EntryListing $listing,
        protected ThemeManager $themes,
        protected ThemeResponse $theme,
    ) {}

    /**
     * Export the site to a folder. Returns the written files as URL path => file path.
     *
     * @return array<string, string>
     */
    public function export(string $directory): array
    {
        $this->written = [];
        $directory = rtrim($directory, '/');
        $this->files->ensureDirectoryExists($directory);

        foreach ($this->paths() as $path => $pages) {
            for ($page = 1; $page <= $pages; $page++) {
                $this->exportPage($directory, $path, $page);
            }
        }

        $this->exportNotFoundPage($directory);

        return $this->written;
    }

    /**
     * Every public path, with its number of list pages (1 for single pages).
     *
     * @return array<string, int>
     */
    public function paths(): array
    {
        $paths = ['/' => $this->pageCount(Entry::query()->ofType((string) config('verso.themes.home_content_type')))];

        foreach ($this->types->all() as $type) {
            $archive = app(Permalinks::class)->archivePath($type);

            if ($archive !== null) {
                $paths[$archive] = $this->pageCount(Entry::query()->ofType($type->name));
            }
        }

        Entry::query()->published()->with('parent')->orderBy('id')->each(function (Entry $entry) use (&$paths): void {
            $paths[$entry->path()] ??= 1;
        });

        Term::query()->orderBy('id')->each(function (Term $term) use (&$paths): void {
            if ($this->taxonomies->has($term->taxonomy)) {
                $count = $this->pageCount(Entry::query()->whereHas('terms', fn (Builder $terms) => $terms->whereKey($term->id)));
                $paths[$term->path()] = max(1, $count);
            }
        });

        $paths['/feed'] = 1;

        return $paths;
    }

    protected function exportPage(string $directory, string $path, int $page): void
    {
        $uri = $page > 1 ? $path.'?page='.$page : $path;
        $request = Request::create(url($uri), 'GET');
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        if ($response->getStatusCode() !== 200) {
            return;
        }

        $target = $this->targetFile($path, $page, $response);
        $this->write($directory, $target, $this->rewriteLinks((string) $response->getContent()));
        $this->written[$uri] = $target;
    }

    protected function exportNotFoundPage(string $directory): void
    {
        $this->themes->registerViews();

        $this->write($directory, '404.html', (string) $this->theme->notFound()->getContent());
        $this->written['/404'] = '404.html';
    }

    // "/" becomes "index.html", "/about/team" becomes "about/team/index.html" and the feed "feed.xml".
    protected function targetFile(string $path, int $page, Response $response): string
    {
        $base = trim($path, '/');

        if (! str_contains((string) $response->headers->get('Content-Type'), 'html')) {
            return $base.'.xml';
        }

        if ($page > 1) {
            $base = ltrim($base.'/page/'.$page, '/');
        }

        return ltrim($base.'/index.html', '/');
    }

    // Turn "?page=2" links into folder links, like "/blog/page/2/".
    public function rewriteLinks(string $html): string
    {
        return (string) preg_replace_callback(
            '#href="([^"?]*)\?page=(\d+)"#',
            function (array $match): string {
                $base = rtrim($match[1], '/');

                return (int) $match[2] <= 1
                    ? 'href="'.($base === '' ? '/' : $base).'"'
                    : 'href="'.$base.'/page/'.$match[2].'/"';
            },
            $html,
        );
    }

    protected function write(string $directory, string $file, string $content): void
    {
        if (str_contains($file, '..')) {
            throw new RuntimeException("Refusing to write outside the export folder: [{$file}].");
        }

        $target = $directory.'/'.$file;
        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->put($target, $content);
    }

    /** @param Builder<Entry> $query */
    protected function pageCount(Builder $query): int
    {
        $total = $query->published()->count();

        return max(1, (int) ceil($total / $this->listing->perPage()));
    }
}
