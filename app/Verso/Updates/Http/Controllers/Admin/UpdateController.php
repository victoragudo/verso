<?php

namespace App\Verso\Updates\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Verso\Updates\Enums\AutomaticUpdates;
use App\Verso\Updates\Enums\UpdateChannel;
use App\Verso\Updates\Enums\UpdateTrigger;
use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Feed\UpdateChecker;
use App\Verso\Updates\Http\Requests\StartUpdateRequest;
use App\Verso\Updates\Http\Requests\UpdateSettingsRequest;
use App\Verso\Updates\Http\UpdatePresenter;
use App\Verso\Updates\Jobs\RunCoreUpdate;
use App\Verso\Updates\Models\CoreUpdate;
use App\Verso\Updates\Support\UpdateSettings;
use App\Verso\Updates\Updater\CoreUpdater;
use App\Verso\Updates\Updater\PackageVerifier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

// The Updates page: current and newest version, changelog, settings, live progress and the update log.
class UpdateController extends Controller
{
    public function index(UpdateChecker $checker, UpdateSettings $settings, CoreUpdater $updater, PackageVerifier $verifier): Response
    {
        $check = $checker->status();
        $recent = CoreUpdate::query()
            ->with('user')
            ->latest('id')
            ->limit(max(1, (int) config('verso.updates.log_limit')))
            ->get();
        $active = $recent->first(fn (CoreUpdate $update): bool => ! $update->status->isFinished()) ?? $recent->first();

        return Inertia::render('admin/updates/index', [
            'check' => [
                'configured' => $checker->isConfigured(),
                'currentVersion' => $check->currentVersion,
                'latest' => $check->latest === null ? null : UpdatePresenter::release($check->latest),
                'updateAvailable' => $check->updateAvailable(),
                'checkedAt' => $check->checkedAt,
                'error' => $check->error,
            ],
            'settings' => [
                'channel' => $settings->channel()->value,
                'automatic' => $settings->automatic()->value,
            ],
            'channels' => array_map(fn (UpdateChannel $channel): array => [
                'value' => $channel->value, 'label' => $channel->label(), 'description' => $channel->description(),
            ], UpdateChannel::cases()),
            'automaticOptions' => array_map(fn (AutomaticUpdates $option): array => [
                'value' => $option->value, 'label' => $option->label(), 'description' => $option->description(),
            ], AutomaticUpdates::cases()),
            'databaseBackupProblem' => $updater->databaseBackupProblem(),
            'signatureRequired' => $verifier->requiresSignature(),
            'activeUpdate' => $active === null ? null : UpdatePresenter::detail($active),
            'updates' => $recent->map(UpdatePresenter::summary(...))->all(),
            'pollInterval' => max(250, (int) config('verso.updates.poll_interval')),
        ]);
    }

    // Ask the release feed now instead of waiting for the daily check.
    public function check(UpdateChecker $checker): RedirectResponse
    {
        $check = $checker->check();

        Inertia::flash('toast', match (true) {
            $check->error !== null => ['type' => 'error', 'message' => $check->error],
            $check->updateAvailable() => ['type' => 'success', 'message' => __('Verso :version is available.', ['version' => $check->latest?->version])],
            default => ['type' => 'success', 'message' => __('Verso :version is up to date.', ['version' => $check->currentVersion])],
        });

        return to_route('admin.updates.index');
    }

    public function store(StartUpdateRequest $request, UpdateChecker $checker, CoreUpdater $updater): RedirectResponse
    {
        $release = $checker->status()->availableRelease();

        if ($release === null || $release->version !== $request->string('version')->toString()) {
            return $this->failed(__('This release is no longer the newest one. Check for updates again.'));
        }

        try {
            $update = $updater->start($release, UpdateTrigger::Admin, $request->user(), $request->boolean('allow_without_database_backup'));
        } catch (UpdateFailed $exception) {
            return $this->failed($exception->getMessage());
        }

        if (config('verso.updates.run_after_response')) {
            RunCoreUpdate::dispatchAfterResponse($update->id);
        } else {
            RunCoreUpdate::dispatch($update->id)->onQueue(config('verso.updates.queue'));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Updating to Verso :version. Keep this page open to follow it.', ['version' => $release->version])]);

        return to_route('admin.updates.index');
    }

    public function settings(UpdateSettingsRequest $request, UpdateSettings $settings): RedirectResponse
    {
        $settings->save(
            UpdateChannel::from($request->string('channel')->toString()),
            AutomaticUpdates::from($request->string('automatic')->toString()),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Update settings saved.')]);

        return to_route('admin.updates.index');
    }

    protected function failed(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('admin.updates.index');
    }
}
