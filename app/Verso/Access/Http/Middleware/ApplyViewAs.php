<?php

namespace App\Verso\Access\Http\Middleware;

use App\Models\User;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\ViewAs;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// While an administrator previews a role, the admin answers as that role and nothing can be saved.
class ApplyViewAs
{
    // The only change allowed during a preview: leaving it.
    public const EXIT_ROUTE = 'admin.view-as.stop';

    public function __construct(
        protected ViewAs $viewAs,
        protected Roles $roles,
        protected UserPermissions $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $request->hasSession() ? $request->session()->get(ViewAs::SESSION_KEY) : null;

        // Start each request clean, also when the app lives on between requests (Octane, tests).
        if ($this->viewAs->active()) {
            $this->viewAs->stop();
            $this->permissions->forget();
        }

        if (! $user instanceof User || ! is_string($role)) {
            return $next($request);
        }

        // Only real administrators may preview, and only roles that still exist.
        if (! $user->isAdmin() || ! $this->roles->exists($role)) {
            $request->session()->forget(ViewAs::SESSION_KEY);

            return $next($request);
        }

        $this->viewAs->start($user->id, $role);
        $this->permissions->forget($user);

        if (! $request->isMethodSafe() && $request->route()?->getName() !== self::EXIT_ROUTE) {
            return $this->refuse($request);
        }

        return $next($request);
    }

    protected function refuse(Request $request): Response
    {
        $message = __('This is a read only preview. Exit the preview to make changes.');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['message' => $message], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back(303);
    }
}
