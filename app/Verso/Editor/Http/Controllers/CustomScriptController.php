<?php

namespace App\Verso\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\CustomCode\CustomCodeStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Serves custom JavaScript as an external file, so pages never print admin scripts inline.
class CustomScriptController extends Controller
{
    public function __invoke(Request $request, CustomCodeStore $store, string $scope): Response
    {
        $entryId = $scope === 'site' ? null : (int) substr($scope, strlen('entry-'));

        if ($entryId !== null) {
            $entry = Entry::query()->find($entryId);
            abort_if($entry === null || (! $entry->isPublished() && ! $request->user()?->can('update', $entry)), 404);
        }

        $code = $store->find($entryId);
        abort_if($code === null || $code->js === null, 404);

        return response($code->js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age='.(int) config('verso.builder.custom_code.cache_seconds'),
        ]);
    }
}
