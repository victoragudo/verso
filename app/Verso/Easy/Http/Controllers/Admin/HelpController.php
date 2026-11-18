<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Easy\Help\HelpTopics;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

// The topics of the help panel, loaded when the panel opens.
class HelpController extends Controller
{
    public function index(#[CurrentUser] User $user, HelpTopics $topics): JsonResponse
    {
        return response()->json(['topics' => $topics->forUser($user)]);
    }
}
