<?php

namespace App\Verso\Seo\Links;

use App\Verso\Core\Models\Redirect;
use App\Verso\Site\Permalinks\PermalinkResolver;
use App\Verso\Site\Permalinks\ResolvedPath;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Tells whether a path of this site shows a page, redirects, or is not found. Answers are kept for the run.
class LinkTargetResolver
{
    // Name of the catch-all public route that shows entries, archives and terms.
    public const CATCH_ALL_ROUTE = 'theme.page';

    /** @var array<string, LinkTarget> */
    protected array $known = [];

    public function __construct(
        protected PermalinkResolver $permalinks,
        protected Router $router,
        protected string $publicPath,
    ) {}

    public function resolve(string $path): LinkTarget
    {
        return $this->known[$path] ??= $this->find($path);
    }

    protected function find(string $path): LinkTarget
    {
        if ($path === '/') {
            return new LinkTarget(LinkStatus::Ok);
        }

        if (Redirect::findForPath($path) !== null) {
            return new LinkTarget(LinkStatus::Redirect);
        }

        if ($this->isFile($path)) {
            return new LinkTarget(LinkStatus::Ok);
        }

        $route = $this->routeName($path);

        if ($route === false) {
            return new LinkTarget(LinkStatus::Broken);
        }

        if ($route !== self::CATCH_ALL_ROUTE) {
            return new LinkTarget(LinkStatus::Ok);
        }

        $resolved = $this->permalinks->resolve($path);

        return match (true) {
            $resolved === null => new LinkTarget(LinkStatus::Broken),
            $resolved->is(ResolvedPath::REDIRECT) => new LinkTarget(LinkStatus::Redirect),
            $resolved->is(ResolvedPath::ENTRY) => new LinkTarget(LinkStatus::Ok, $resolved->entry?->id),
            default => new LinkTarget(LinkStatus::Ok),
        };
    }

    // The name of the GET route for the path ("" when it has none), or false when no route matches.
    protected function routeName(string $path): string|false
    {
        try {
            return (string) $this->router->getRoutes()->match(Request::create($path, 'GET'))->getName();
        } catch (HttpException) {
            return false;
        }
    }

    // Files in the public folder, like "/storage/menu.pdf". Paths with ".." are never read.
    protected function isFile(string $path): bool
    {
        if (str_contains($path, '..')) {
            return false;
        }

        return is_file(rtrim($this->publicPath, '/').'/'.ltrim($path, '/'));
    }
}
