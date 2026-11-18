<?php

namespace App\Verso\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Actions\RestoreRevision;
use App\Verso\Content\ContentType;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

// Puts an old revision back on an entry.
class EntryRevisionController extends Controller
{
    public function __invoke(RestoreRevision $restoreRevision, #[CurrentUser] User $user, ContentType $contentType, Entry $entry, Revision $revision): RedirectResponse
    {
        abort_unless($entry->type === $contentType->name && $revision->entry_id === $entry->id, 404);
        Gate::authorize('update', $entry);

        $restoreRevision->handle($entry, $revision, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Revision restored.')]);

        return to_route('admin.entries.edit', ['contentType' => $contentType->name, 'entry' => $entry->id]);
    }
}
