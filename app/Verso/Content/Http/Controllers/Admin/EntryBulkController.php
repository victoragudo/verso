<?php

namespace App\Verso\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Actions\RestoreEntry;
use App\Verso\Content\Actions\TrashEntry;
use App\Verso\Content\Admin\ContentLabels;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\I18n\Translate;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

// Trashes or restores many entries at once. Entries the user may not touch are skipped.
class EntryBulkController extends Controller
{
    public function __invoke(Request $request, TrashEntry $trashEntry, RestoreEntry $restoreEntry, #[CurrentUser] User $user, ContentType $contentType, ContentLabels $labels): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['trash', 'restore'])],
            'ids' => ['required', 'array', 'max:'.(int) config('verso.admin.per_page')],
            'ids.*' => ['integer'],
        ]);

        $entries = Entry::withTrashed()
            ->ofType($contentType->name)
            ->whereKey($validated['ids'])
            ->get();

        $done = 0;

        foreach ($entries as $entry) {
            if ($validated['action'] === 'trash' && $user->can('delete', $entry) && ! $entry->trashed()) {
                $trashEntry->handle($entry);
                $done++;
            }

            if ($validated['action'] === 'restore' && $user->can('restore', $entry) && $entry->trashed()) {
                $restoreEntry->handle($entry);
                $done++;
            }
        }

        $message = $validated['action'] === 'trash'
            ? '{count, plural, one {# {type} moved to the trash.} other {# {types} moved to the trash.}}'
            : '{count, plural, one {# {type} restored.} other {# {types} restored.}}';
        Inertia::flash('toast', ['type' => 'success', 'message' => Translate::icu($message, [
            'count' => $done,
            'type' => $labels->inline($labels->typeLabel($contentType)),
            'types' => $labels->inline($labels->typePluralLabel($contentType)),
        ])]);

        return back();
    }
}
