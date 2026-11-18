<?php

namespace App\Verso\Editor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\CustomCode\CustomCode;
use App\Verso\Editor\CustomCode\CustomCodeAccess;
use App\Verso\Editor\CustomCode\CustomCodeStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Custom CSS and JavaScript for the whole site (an admin page) and for one entry (JSON for the editor).
class CustomCodeController extends Controller
{
    public function __construct(
        protected CustomCodeStore $store,
        protected CustomCodeAccess $access,
    ) {}

    public function edit(Request $request): Response
    {
        $can = $this->can($request->user(), null);
        $code = $this->store->find(null);

        return Inertia::render('admin/editor/custom-code', [
            'css' => $code->css ?? '',
            'js' => $code->js ?? '',
            'can' => $can,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->saveFrom($request, null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom code saved.')]);

        return to_route('admin.editor.code.edit');
    }

    public function showEntry(Request $request, Entry $entry): JsonResponse
    {
        $can = $this->can($request->user(), $entry);
        $code = $this->store->find($entry->id);

        return response()->json(['css' => $code->css ?? '', 'js' => $code->js ?? '', 'can' => $can]);
    }

    public function updateEntry(Request $request, Entry $entry): JsonResponse
    {
        $code = $this->saveFrom($request, $entry);

        return response()->json(['css' => $code->css ?? '', 'js' => $code->js ?? '']);
    }

    /** @return array{css: bool, js: bool} */
    protected function can(mixed $user, ?Entry $entry): array
    {
        $user = $user instanceof User ? $user : null;
        abort_if($user === null || ($entry !== null && ! $user->can('update', $entry)), 403);

        $can = ['css' => $this->access->css($user, $entry?->type), 'js' => $this->access->js($user, $entry?->type)];
        abort_unless($can['css'] || $can['js'], 403);

        return $can;
    }

    // Saves only the parts the user may change; the other part keeps its saved value.
    protected function saveFrom(Request $request, ?Entry $entry): ?CustomCode
    {
        $can = $this->can($request->user(), $entry);
        $data = $request->validate([
            'css' => ['nullable', 'string', 'max:'.(int) config('verso.builder.custom_code.css_max')],
            'js' => ['nullable', 'string', 'max:'.(int) config('verso.builder.custom_code.js_max')],
        ]);
        $user = $request->user();

        return $this->store->save(
            $entry,
            $can['css'] ? (string) ($data['css'] ?? '') : null,
            $can['js'] ? (string) ($data['js'] ?? '') : null,
            $user instanceof User ? $user : null,
        );
    }
}
