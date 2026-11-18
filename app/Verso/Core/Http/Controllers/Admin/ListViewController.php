<?php

namespace App\Verso\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Saves the columns and density a user picked for one admin list.
class ListViewController extends Controller
{
    public function update(Request $request, #[CurrentUser] User $user, string $list): Response
    {
        $data = $request->validate([
            'columns' => ['present', 'nullable', 'array', 'list', 'max:'.max(1, (int) config('verso.admin.lists.max_columns'))],
            'columns.*' => ['string', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'density' => ['nullable', 'string', 'in:'.implode(',', AdminPreferences::DENSITIES)],
        ]);

        AdminPreferences::of($user)->saveListView($list, $data['columns'] ?? null, $data['density'] ?? null);

        return response()->noContent();
    }
}
