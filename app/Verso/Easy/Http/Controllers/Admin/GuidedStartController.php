<?php

namespace App\Verso\Easy\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Easy\Actions\InstallStarterSite;
use App\Verso\Easy\Http\Requests\InstallStarterRequest;
use App\Verso\Easy\Starters\StarterSiteRegistry;
use App\Verso\Easy\Support\EasyState;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

// The guided start: "What is your site for?" with one ready made site per answer.
class GuidedStartController extends Controller
{
    public function __construct(
        protected StarterSiteRegistry $starters,
        protected EasyState $state,
    ) {}

    public function show(): Response
    {
        return Inertia::render('admin/easy/start', [
            'starters' => $this->starters->toArray(),
            'current' => $this->state->starter(),
            'hasPages' => Entry::query()->ofType('page')->exists(),
        ]);
    }

    public function store(InstallStarterRequest $request, InstallStarterSite $install): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $report = $install->handle($user, (string) $request->validated('starter'));
        Inertia::flash('toast', ['type' => 'success', 'message' => $report->message()]);

        return to_route($user->can(Capability::ManageThemes->value) ? 'admin.easy.style' : 'admin.dashboard');
    }
}
