<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use App\Verso\Extensions\Actions\VerifyInstalledTheme;
use App\Verso\Site\Parts\ChildThemeFromChanges;
use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use InvalidArgumentException;

// "Create child theme from these changes" in the Site Editor: writes the new theme, checks it and can switch to it.
class ChildThemeController extends Controller
{
    // Longest theme name people can type.
    protected const NAME_MAX = 80;

    public function store(Request $request, ThemeManager $themes, ChildThemeFromChanges $builder, VerifyInstalledTheme $verify, ActivateTheme $activate): RedirectResponse
    {
        Gate::authorize(Capability::ManageThemes->value);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'slug' => ['nullable', 'string', 'max:'.self::NAME_MAX],
            'activate' => ['sometimes', 'boolean'],
        ]);

        $slug = Str::slug(($data['slug'] ?? '') !== '' ? $data['slug'] : $data['name']);
        $parent = $themes->active();

        try {
            $child = $builder->handle($parent, trim($data['name']), $slug, (string) $request->user()?->name);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()]);
        }

        $verification = $verify->handle($child, $request->user()?->id);

        if ($request->boolean('activate')) {
            $activate->handle($child->slug);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$child->name} was created in themes/{$child->slug}".($request->boolean('activate') ? ' and is now active' : '').'. Checks: '.strtolower($verification->risk->label()).'.',
        ]);

        return to_route('admin.themes.show', $child->slug);
    }
}
