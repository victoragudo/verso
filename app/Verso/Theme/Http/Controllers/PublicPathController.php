<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Content\Models\Entry;
use App\Verso\Site\Permalinks\PermalinkResolver;
use App\Verso\Site\Permalinks\ResolvedPath;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// The catch-all route: shows the archive, term or entry behind a path, from the permalink settings.
class PublicPathController extends Controller
{
    // Request attribute that tells other code (like body classes) what the page shows.
    public const ATTRIBUTE = 'verso.resolved';

    public function __construct(
        protected PermalinkResolver $resolver,
        protected ThemeResponse $theme,
    ) {}

    public function __invoke(Request $request, string $path): Response|RedirectResponse
    {
        $resolved = $this->resolver->resolve($path);

        abort_if($resolved === null, 404);

        $request->attributes->set(self::ATTRIBUTE, $resolved);

        return match ($resolved->kind) {
            ResolvedPath::REDIRECT => $this->redirect($request, (string) $resolved->redirectTo),
            ResolvedPath::ARCHIVE => app()->call([app(ArchiveController::class), '__invoke'], ['contentType' => $resolved->type]),
            ResolvedPath::TERM => app()->call([app(TermController::class), '__invoke'], ['taxonomy' => $resolved->taxonomy, 'term' => $resolved->term]),
            default => $this->entry($resolved->entry),
        };
    }

    protected function entry(?Entry $entry): Response|RedirectResponse
    {
        abort_if($entry === null, 404);

        return $this->theme->entry($entry);
    }

    // Keeps the query string, like "?page=2".
    protected function redirect(Request $request, string $path): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect(url($path).($query === null ? '' : '?'.$query), 301);
    }
}
