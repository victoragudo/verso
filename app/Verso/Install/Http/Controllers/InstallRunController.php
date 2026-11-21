<?php

namespace App\Verso\Install\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Install\Pipeline\InstallPipeline;
use App\Verso\Install\Pipeline\InstallProgress;
use App\Verso\Install\Wizard\WizardStep;
use App\Verso\Install\Wizard\WizardStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Runs the install one step per request, so the page can show live progress and retry a failed step.
class InstallRunController extends Controller
{
    public function __construct(
        protected WizardStore $store,
        protected InstallPipeline $pipeline,
    ) {}

    public function run(InstallProgress $progress): JsonResponse
    {
        if (! $this->store->isDone(WizardStep::Review)) {
            return response()->json(['message' => __('Review your choices before you install.')], 409);
        }

        $plan = $this->store->plan();
        $status = $this->pipeline->runNext($plan);

        // The page waits and asks again, the same as when the server does not answer.
        if ($status->waitingForRestart) {
            return response()->json(['message' => __('The server is restarting with the new settings. Trying again...')], 503);
        }

        if (! $status->finished()) {
            return response()->json($status->toArray());
        }

        $admin = User::query()->where('email', $plan->admin->email)->firstOrFail();
        $nonce = Str::random(40);
        $progress->rememberLogin((int) $admin->getKey(), $nonce);
        $this->store->forget();

        return response()->json([
            ...$status->toArray(),
            'links' => [
                'site' => $plan->site->url,
                'admin' => URL::temporarySignedRoute(
                    'verso.welcome',
                    now()->addMinutes((int) config('verso.install.login_link_minutes')),
                    ['user' => $admin->getKey(), 'nonce' => $nonce],
                    absolute: false,
                ),
            ],
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json($this->pipeline->status()->toArray());
    }
}
