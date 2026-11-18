<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Easy\Actions\SaveEasyPage;
use App\Verso\Easy\Drafts\PageDrafts;
use App\Verso\Easy\Http\Requests\EasyPageRequest;
use App\Verso\Easy\Pages\PageFrame;
use App\Verso\Easy\Pages\PageVersions;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Styles\ThemeStyleOptions;
use App\Verso\Editor\Builder\EditorManifest;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

// The visual page editor: the real page in a frame, with click to edit text, pictures and ready sections.
class PageEditorController extends Controller
{
    public function edit(Request $request, Entry $entry, ContentTypeRegistry $types, SectionLibrary $sections, EditorManifest $manifest, PageDrafts $drafts, ThemeStyleOptions $styles, ThemeManager $themes, ContentLabels $labels): Response
    {
        Gate::authorize('update', $entry);

        /** @var User $user */
        $user = $request->user();
        $type = $types->find($entry->type);
        $draft = $drafts->newerThanPage($entry, $user);

        return Inertia::render('admin/easy/page-editor', [
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'type' => $entry->type,
                'typeLabel' => $type === null ? __('Page') : $labels->typeLabel($type),
                'status' => $entry->status->value,
                'url' => $entry->isPublished() ? $entry->url() : null,
                'backUrl' => $entry->isPublished() ? $entry->url() : route('admin.entries.index', ['contentType' => $entry->type]),
                'advancedUrl' => route('admin.entries.edit', ['contentType' => $entry->type, 'entry' => $entry->id]),
                'canPublish' => $user->can('publish', $entry),
                'isFrontPage' => $entry->isFrontPage(),
                'updatedAt' => $entry->updated_at?->toIso8601String(),
            ],
            'blocks' => $entry->blocks ?? [],
            'draft' => $draft === null ? null : $drafts->toArray($draft),
            'sections' => $sections->toArray(),
            'editor' => $manifest->for($user),
            'previewWidths' => config('verso.site_parts.preview_widths'),
            'renderDelay' => (int) config('verso.easy.page_editor.render_delay'),
            'undoSteps' => (int) config('verso.easy.page_editor.undo_steps'),
            'undoSeconds' => (int) config('verso.easy.page_editor.undo_seconds'),
            'autosaveDelay' => (int) config('verso.easy.page_editor.autosave_delay'),
            'colors' => $styles->colorValues($themes->active()),
        ]);
    }

    // Keeps the unsaved blocks as an automatic draft for this person. The page and its revisions stay as they are.
    public function saveDraft(EasyPageRequest $request, PageDrafts $drafts, Entry $entry): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($drafts->toArray($drafts->save($entry, $user, $request->blocks())));
    }

    // "Throw them away": removes the automatic draft of this person.
    public function discardDraft(Request $request, PageDrafts $drafts, Entry $entry): JsonResponse
    {
        Gate::authorize('update', $entry);

        /** @var User $user */
        $user = $request->user();
        $drafts->clear($entry, $user);

        return response()->json(['cleared' => true]);
    }

    // The earlier versions of the page for "Restore this page".
    public function versions(PageVersions $versions, Entry $entry): JsonResponse
    {
        Gate::authorize('update', $entry);

        return response()->json(['versions' => $versions->forEntry($entry, (int) config('verso.easy.page_editor.versions'))]);
    }

    // The page drawn by the theme from the blocks in the editor. Nothing is saved.
    public function frame(EasyPageRequest $request, PageFrame $frame, Entry $entry): SymfonyResponse
    {
        return $frame->render($entry, $request->blocks());
    }

    // "Save" keeps the status, "Publish" makes the page public.
    public function update(EasyPageRequest $request, SaveEasyPage $save, Entry $entry): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $publish = $request->boolean('publish');
        $entry = $save->handle($entry, $user, $request->blocks(), $publish, $request->boolean('confirmEmpty'));

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $publish => __('Published. Visitors see your page now.'),
            $entry->isPublished() => __('Saved. Visitors see your changes now.'),
            default => __('Draft saved. Only you can see it.'),
        }]);

        return back();
    }
}
