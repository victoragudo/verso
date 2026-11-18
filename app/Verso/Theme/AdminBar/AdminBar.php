<?php

namespace App\Verso\Theme\AdminBar;

use App\Models\User;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Traits\Localizable;
use Symfony\Component\HttpFoundation\Response;

// Builds the small bar on top of public pages that takes signed in admins to the admin.
// It is added after the page cache, so it is never stored in a cached page.
class AdminBar
{
    use Localizable;

    // Target of the skip link when the theme has no <main id="...">.
    public const FALLBACK_TARGET = 'verso-adminbar-end';

    protected const VIEW = 'verso-theme::admin-bar';

    // Route of the visual page editor of Easy mode. The bar links to it when it exists.
    protected const VISUAL_EDITOR_ROUTE = 'admin.easy.pages.edit';

    protected const MAIN_ID_PATTERN = '/<main\b[^>]*\bid\s*=\s*["\']([^"\']+)["\']/i';

    public function __construct(
        protected ViewFactory $views,
        protected ContentTypeRegistry $types,
        protected ThemeManager $themes,
        protected Settings $settings,
        protected PageCache $cache,
        protected AdminBarContext $context,
        protected ContentLabels $labels,
        protected bool $enabled = true,
        protected int $height = 36,
    ) {}

    // True when this user should see the bar on public pages.
    public function shows(?User $user): bool
    {
        return $this->enabled && $user !== null && $user->seesAdminBar();
    }

    // The bar HTML for the page in the response.
    public function render(Request $request, Response $response): string
    {
        /** @var User $user */
        $user = $request->user();

        return $this->withLocale($user->preferredLocale(), fn (): string => $this->html($request, $response, $user));
    }

    protected function html(Request $request, Response $response, User $user): string
    {
        $editLink = $this->editLink($user);

        return $this->views->make(self::VIEW, [
            'height' => $this->height,
            'siteName' => $this->siteName(),
            'dashboardUrl' => route('admin.dashboard'),
            'newLinks' => $this->newLinks($user),
            'editLink' => $editLink,
            'visualEditUrl' => $this->visualEditUrl($editLink),
            'customizeUrl' => $user->hasCapability(Capability::ManageThemes)
                ? route('admin.themes.customize', ['theme' => $this->themes->active()->slug])
                : null,
            'cacheStatus' => $this->cacheStatus($response),
            'userName' => $user->name,
            'profileUrl' => route('profile.edit'),
            'logoutUrl' => route('logout'),
            'csrfToken' => $request->session()->token(),
            'skipTarget' => $this->skipTarget((string) $response->getContent()),
            'fallbackTarget' => self::FALLBACK_TARGET,
        ])->render();
    }

    protected function siteName(): string
    {
        $name = $this->settings->get('site.name');

        return is_string($name) && $name !== '' ? $name : (string) config('app.name');
    }

    /** @return list<AdminBarLink> */
    protected function newLinks(User $user): array
    {
        if (! $user->can('create', Entry::class)) {
            return [];
        }

        return array_values(array_map(
            fn (ContentType $type): AdminBarLink => new AdminBarLink($this->labels->typeLabel($type), route('admin.entries.create', ['contentType' => $type->name])),
            $this->types->all(),
        ));
    }

    // "Edit post" or "Edit page" when the page shows one entry the user may change.
    protected function editLink(User $user): ?AdminBarLink
    {
        $entry = $this->context->entry();
        $type = $entry === null ? null : $this->types->find($entry->type);

        if ($entry === null || $type === null || ! $user->can('update', $entry)) {
            return null;
        }

        return new AdminBarLink(
            $this->labels->inline($this->labels->typeLabel($type)),
            route('admin.entries.edit', ['contentType' => $type->name, 'entry' => $entry->id]),
        );
    }

    // The visual page editor for the shown entry, when the user may change it and the editor exists.
    protected function visualEditUrl(?AdminBarLink $editLink): ?string
    {
        $entry = $this->context->entry();

        if ($editLink === null || $entry === null || ! Route::has(self::VISUAL_EDITOR_ROUTE)) {
            return null;
        }

        return route(self::VISUAL_EDITOR_ROUTE, ['entry' => $entry->id]);
    }

    // HIT or MISS from the page cache, "bypass" when the page skipped it, "off" when the cache is off.
    protected function cacheStatus(Response $response): string
    {
        $status = $response->headers->get(PageCache::HEADER);

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return $this->cache->enabled() ? 'bypass' : 'off';
    }

    // The skip link goes to the main area of the theme, or to the end of the bar.
    protected function skipTarget(string $html): string
    {
        return preg_match(self::MAIN_ID_PATTERN, $html, $match) === 1 ? $match[1] : self::FALLBACK_TARGET;
    }
}
