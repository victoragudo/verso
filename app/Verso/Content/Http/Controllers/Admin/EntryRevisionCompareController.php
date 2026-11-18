<?php

namespace App\Verso\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Support\BlockDiff;
use App\Verso\Editor\Support\RenderContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

// Compares a revision with the saved entry: both drawn as HTML with block markers, plus the list of changes,
// so the editor can show them side by side with the changed blocks marked.
class EntryRevisionCompareController extends Controller
{
    public function __invoke(BlockDiff $diff, BlockRenderer $renderer, ContentType $contentType, Entry $entry, Revision $revision): JsonResponse
    {
        abort_unless($entry->type === $contentType->name && $revision->entry_id === $entry->id, 404);
        Gate::authorize('update', $entry);

        $before = (array) ($revision->blocks ?? []);
        $after = (array) ($entry->blocks ?? []);
        $markers = $renderer->showsMarkers();
        $renderer->showMarkers();

        try {
            $draw = fn (array $blocks): string => app(RenderContext::class)->withEntry($entry, fn (): string => $renderer->render($blocks));
            $html = ['before' => $draw($before), 'after' => $draw($after)];
        } finally {
            $renderer->showMarkers($markers);
        }

        return response()->json([
            ...$html,
            'title' => ['before' => $revision->title, 'after' => $entry->title],
            'changes' => $diff->compare($before, $after),
        ]);
    }
}
