<?php

namespace Verso\Plugins\AiAssistant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Contracts\AiProvider;
use Verso\Plugins\AiAssistant\Http\Requests\UpdateAiSettingsRequest;
use Verso\Plugins\AiAssistant\Models\AiUsage;

// Admin page with the plugin settings and the token usage per user and day.
class AiAdminController extends Controller
{
    public function index(AiSettings $settings, AiProvider $provider): Response
    {
        $days = max(1, (int) config('ai-assistant.usage_days'));
        $since = Carbon::now('UTC')->subDays($days - 1)->toDateString();

        $usage = AiUsage::query()
            ->with('user:id,name,email')
            ->where('day', '>=', $since)
            ->orderByDesc('day')
            ->orderByDesc('output_tokens')
            ->paginate((int) config('verso.admin.per_page'))
            ->withQueryString()
            ->through(fn (AiUsage $row): array => [
                'id' => $row->id,
                'day' => $row->day->toDateString(),
                'user' => $row->user === null ? __('ai-assistant::Deleted user') : $row->user->name,
                'email' => $row->user?->email,
                'requests' => $row->requests,
                'input_tokens' => $row->input_tokens,
                'output_tokens' => $row->output_tokens,
            ]);

        $totals = AiUsage::query()
            ->where('day', '>=', $since)
            ->selectRaw('coalesce(sum(requests), 0) as requests, coalesce(sum(input_tokens), 0) as input_tokens, coalesce(sum(output_tokens), 0) as output_tokens')
            ->first();

        return Inertia::render('admin/plugins/ai-assistant/index', [
            'settings' => [
                'provider' => $provider->name(),
                'hasKey' => $settings->hasApiKey(),
                'maskedKey' => $settings->maskedApiKey(),
                'model' => $settings->model(),
                'defaultModel' => (string) config('ai-assistant.anthropic.default_model'),
                'maxTokens' => $settings->maxTokens(),
                'maxTokensCeiling' => (int) config('ai-assistant.limits.max_tokens_ceiling'),
                'rateLimit' => $settings->rateLimit(),
                'rateLimitMinutes' => (int) ceil(((int) config('ai-assistant.limits.rate_limit_window')) / 60),
            ],
            'usage' => $usage,
            'totals' => [
                'requests' => (int) ($totals->requests ?? 0),
                'input_tokens' => (int) ($totals->input_tokens ?? 0),
                'output_tokens' => (int) ($totals->output_tokens ?? 0),
            ],
            'days' => $days,
            'urls' => [
                'index' => route('admin.ai-assistant.index'),
                'update' => route('admin.ai-assistant.settings.update'),
                'forgetKey' => route('admin.ai-assistant.settings.forget-key'),
            ],
        ]);
    }

    public function update(UpdateAiSettingsRequest $request, AiSettings $settings): RedirectResponse
    {
        /** @var array{api_key?: string|null, model: string, max_tokens: int, rate_limit: int} $values */
        $values = $request->validated();

        $settings->save($values);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ai-assistant::AI assistant settings saved.')]);

        return back();
    }

    public function forgetKey(AiSettings $settings): RedirectResponse
    {
        $settings->forgetApiKey();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ai-assistant::The API key was removed.')]);

        return back();
    }
}
