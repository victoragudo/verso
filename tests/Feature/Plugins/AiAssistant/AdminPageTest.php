<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AiAssistantFixtures as Ai;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Models\AiUsage;

beforeEach(function () {
    Ai::enable();
    $this->admin = User::factory()->admin()->create(['name' => 'Lucía Ferrer', 'email' => 'lucia@lighthouse-media.example']);
});

it('shows the settings and the usage per user and day', function () {
    $this->travelTo('2026-11-17 12:00:00');
    $writer = User::factory()->editor()->create(['name' => 'Tomás Reyes', 'email' => 'tomas@lighthouse-media.example']);
    AiUsage::query()->create(['user_id' => $writer->id, 'day' => '2026-11-17', 'requests' => 4, 'input_tokens' => 2400, 'output_tokens' => 610]);
    AiUsage::query()->create(['user_id' => $writer->id, 'day' => '2026-11-16', 'requests' => 2, 'input_tokens' => 900, 'output_tokens' => 210]);
    AiUsage::query()->create(['user_id' => $this->admin->id, 'day' => '2026-11-17', 'requests' => 1, 'input_tokens' => 300, 'output_tokens' => 80]);

    $this->actingAs($this->admin)
        ->get(route('admin.ai-assistant.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/ai-assistant/index')
            ->where('settings.provider', 'Anthropic')
            ->where('settings.hasKey', true)
            ->where('settings.maskedKey', '••••••••Q9xZ')
            ->where('settings.model', 'claude-sonnet-5-5')
            ->where('settings.defaultModel', 'claude-sonnet-5-5')
            ->where('settings.maxTokens', 4000)
            ->where('settings.rateLimit', 30)
            ->where('settings.rateLimitMinutes', 60)
            ->has('usage.data', 3)
            ->where('usage.data.0.day', '2026-11-17')
            ->where('usage.data.0.user', 'Tomás Reyes')
            ->where('usage.data.0.email', 'tomas@lighthouse-media.example')
            ->where('usage.data.0.input_tokens', 2400)
            ->where('usage.data.0.output_tokens', 610)
            ->where('usage.data.2.day', '2026-11-16')
            ->where('totals.requests', 7)
            ->where('totals.input_tokens', 3600)
            ->where('totals.output_tokens', 900)
            ->where('days', 30)
            ->where('urls.update', route('admin.ai-assistant.settings.update')));
});

it('never sends the API key to the page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.ai-assistant.index'))
        ->assertOk()
        ->assertDontSee(Ai::API_KEY);
});

it('leaves out usage older than the configured days', function () {
    config(['ai-assistant.usage_days' => 7]);
    $this->travelTo('2026-11-17 12:00:00');
    AiUsage::query()->create(['user_id' => $this->admin->id, 'day' => '2026-11-11', 'requests' => 1, 'input_tokens' => 100, 'output_tokens' => 20]);
    AiUsage::query()->create(['user_id' => $this->admin->id, 'day' => '2026-11-10', 'requests' => 9, 'input_tokens' => 9000, 'output_tokens' => 900]);

    $this->actingAs($this->admin)
        ->get(route('admin.ai-assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('usage.data', 1)
            ->where('usage.data.0.day', '2026-11-11')
            ->where('totals.requests', 1)
            ->where('days', 7));
});

it('shows an empty usage list', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.ai-assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('usage.data', 0)
            ->where('totals.requests', 0));
});

it('forbids the page to editors and authors', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('admin.ai-assistant.index'))
        ->assertForbidden();
})->with(['editor', 'author']);

it('saves the settings and keeps the key encrypted', function () {
    app(AiSettings::class)->forgetApiKey();

    $this->actingAs($this->admin)
        ->from(route('admin.ai-assistant.index'))
        ->put(route('admin.ai-assistant.settings.update'), [
            'api_key' => 'sk-ant-api03-north-pier-55Hw',
            'model' => 'claude-opus-5-5',
            'max_tokens' => 8000,
            'rate_limit' => 50,
        ])
        ->assertRedirect(route('admin.ai-assistant.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'AI assistant settings saved.']);

    $settings = app(AiSettings::class);

    expect($settings->apiKey())->toBe('sk-ant-api03-north-pier-55Hw')
        ->and(app(Settings::class)->get(AiSettings::API_KEY))->not->toContain('north-pier')
        ->and($settings->model())->toBe('claude-opus-5-5')
        ->and($settings->maxTokens())->toBe(8000)
        ->and($settings->rateLimit())->toBe(50);
});

it('keeps the saved key when the key field is left empty', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.ai-assistant.settings.update'), [
            'api_key' => '',
            'model' => 'claude-haiku-5-5',
            'max_tokens' => 2000,
            'rate_limit' => 10,
        ])
        ->assertSessionHasNoErrors();

    expect(app(AiSettings::class)->apiKey())->toBe(Ai::API_KEY);
});

it('checks the settings form', function (array $values, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.ai-assistant.settings.update'), [
            'model' => 'claude-sonnet-5-5',
            'max_tokens' => 4000,
            'rate_limit' => 30,
            ...$values,
        ])
        ->assertSessionHasErrors($field);
})->with([
    'missing model' => [['model' => ''], 'model'],
    'model with spaces' => [['model' => 'claude sonnet'], 'model'],
    'zero max tokens' => [['max_tokens' => 0], 'max_tokens'],
    'max tokens over the ceiling' => [['max_tokens' => 64001], 'max_tokens'],
    'zero rate limit' => [['rate_limit' => 0], 'rate_limit'],
    'text rate limit' => [['rate_limit' => 'many'], 'rate_limit'],
]);

it('reads the max tokens ceiling from config', function () {
    config(['ai-assistant.limits.max_tokens_ceiling' => 2000]);

    $this->actingAs($this->admin)
        ->put(route('admin.ai-assistant.settings.update'), ['model' => 'claude-sonnet-5-5', 'max_tokens' => 2500, 'rate_limit' => 30])
        ->assertSessionHasErrors('max_tokens');
});

it('removes the API key', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.ai-assistant.settings.forget-key'))
        ->assertRedirect();

    expect(app(AiSettings::class)->hasApiKey())->toBeFalse();
});

it('forbids editors to change the settings', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->put(route('admin.ai-assistant.settings.update'), ['model' => 'claude-opus-5-5', 'max_tokens' => 4000, 'rate_limit' => 999])
        ->assertForbidden();

    expect(app(AiSettings::class)->rateLimit())->toBe(30);
});
