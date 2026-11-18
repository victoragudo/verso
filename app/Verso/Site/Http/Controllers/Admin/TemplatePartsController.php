<?php

namespace App\Verso\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Core\Enums\Capability;
use App\Verso\Theme\Templates\PageTemplates;
use App\Verso\Theme\Templates\TemplatePartChoices;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

// Saves the header and footer styles of one page template of the active theme, like a minimal header for landing pages.
class TemplatePartsController extends Controller
{
    public function update(Request $request, ThemeManager $themes, PageTemplates $templates, TemplatePartChoices $choices, string $template): RedirectResponse
    {
        Gate::authorize(Capability::EditSite->value);

        $theme = $themes->active();
        $found = collect($templates->all($theme))->firstWhere('name', $template) ?? abort(404);
        $offered = collect($choices->options($theme))->keyBy('name');

        $data = $request->validate([
            'parts' => ['present', 'array'],
            'parts.*' => ['nullable', 'string', 'max:60'],
        ]);

        foreach ($data['parts'] as $part => $style) {
            if ($style !== null && ! collect($offered[$part]['options'] ?? [])->contains('value', $style)) {
                return back()->withErrors(['parts.'.$part => __('Choose a style offered by the theme.')]);
            }
        }

        $choices->saveForTemplate($theme, $found->name, array_filter($data['parts'], is_string(...)));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Header and footer saved for ":template".', ['template' => $found->label])]);

        return back();
    }
}
