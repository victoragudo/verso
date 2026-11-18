<?php

namespace App\Verso\Editor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\Roles;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\Builder\EditorFeature;
use App\Verso\Editor\Builder\EditorFeatureRegistry;
use App\Verso\Editor\Builder\ThemeEditorLimits;
use App\Verso\Editor\Http\Requests\EditorSettingsRequest;
use App\Verso\I18n\Translate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// The "Editor settings" admin page: which editor tools each role has and which blocks each content type allows.
class EditorSettingsController extends Controller
{
    public function edit(EditorAccess $access, EditorFeatureRegistry $features, ContentTypeRegistry $types, ThemeEditorLimits $theme, ContentLabels $labels): Response
    {
        Gate::authorize(Capability::ManageEditorSettings->value);

        $saved = $access->savedBlocks();

        return Inertia::render('admin/editor/settings', [
            'features' => array_map(fn (EditorFeature $feature): array => [
                ...$feature->toArray(),
                'label' => Translate::text($feature->label),
                'description' => Translate::text($feature->description),
                'roles' => $access->roles($feature->key),
                'types' => $access->types($feature->key),
            ], $features->all()),
            'roles' => array_values(array_map(fn (RoleDefinition $role): array => ['value' => $role->slug, 'label' => $role->name], app(Roles::class)->all())),
            'contentTypes' => array_values(array_map(fn (ContentType $type): array => [
                'name' => $type->name,
                'label' => $labels->typePluralLabel($type),
                'blocks' => $saved[$type->name] ?? null,
            ], $types->all())),
            'blocks' => app(BlockRegistry::class)->names(),
            'themeBlocks' => $theme->blocks(),
        ]);
    }

    public function update(EditorSettingsRequest $request, EditorAccess $access): RedirectResponse
    {
        $access->save($request->features(), $request->blocks());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Editor settings saved.')]);

        return to_route('admin.editor.settings.edit');
    }
}
