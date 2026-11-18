<?php

namespace App\Http\Middleware;

use App\Verso\Access\Http\Presenters\SharedAccess;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavNewBadges;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\SiteTime;
use App\Verso\I18n\I18nProps;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'versoVersion' => fn (): string => (string) config('verso.version', ''),
            'auth' => [
                'user' => $request->user(),
            ],
            // Capability key => true or false, for showing and hiding buttons. The server checks again.
            'can' => fn (): array => app(SharedAccess::class)->can($request->user()),
            'viewAs' => fn (): ?array => app(SharedAccess::class)->viewAs(),
            'siteName' => fn (): string => $request->user() === null
                ? (string) config('app.name')
                : (string) (app(Settings::class)->get('site.name') ?? config('app.name')),
            'siteTimezone' => fn (): string => app(SiteTime::class)->timezone(),
            'versoNav' => fn (): array => app(NavNewBadges::class)->apply($request->user(), AdminNav::forUser($request->user()), $request->getPathInfo()),
            'versoSlots' => fn (): array => app(AdminSlotRegistry::class)->forRequest($request),
            'i18n' => fn (): array => app(I18nProps::class)->forRequest($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
