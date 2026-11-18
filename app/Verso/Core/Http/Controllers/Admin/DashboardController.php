<?php

namespace App\Verso\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Core\Admin\Dashboard;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

// Shows the admin home page with counts and short lists.
class DashboardController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Dashboard $dashboard): Response
    {
        return Inertia::render('admin/core/dashboard', [
            'stats' => $dashboard->statsFor($user),
            'panels' => $dashboard->panelsFor($user),
            'notices' => $dashboard->noticesFor($user),
        ]);
    }
}
