<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\PluginManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\AiAssistantFixtures as Ai;
use Verso\Plugins\AiAssistant\AiGuard;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Models\AiUsage;

beforeEach(function () {
    Ai::enable();
});

// Ask for a shorter version of a sentence, as the given user.
function shorten(User $user): TestResponse
{
    return test()->actingAs($user)->postJson(route('admin.ai-assistant.actions.rewrite'), [
        'text' => 'The ferry to Cacilhas leaves every twenty minutes from Cais do Sodré.',
        'mode' => 'shorten',
    ]);
}

it('lets editors and admins use the assistant', function (string $role) {
    Ai::fake(Ai::reply('Ferries leave every 20 minutes.'));

    shorten(User::factory()->{$role}()->create())->assertOk();
})->with(['editor', 'admin']);

it('forbids authors and contributors', function (string $role) {
    Http::fake();

    shorten(User::factory()->{$role}()->create())->assertForbidden();

    Http::assertNothingSent();
})->with(['author', 'contributor']);

it('forbids subscribers', function () {
    Http::fake();

    shorten(User::factory()->create(['role' => 'subscriber']))->assertForbidden();

    Http::assertNothingSent();
});

it('sends guests to the login page', function () {
    $this->post(route('admin.ai-assistant.actions.rewrite'), ['text' => 'A quiet harbor.', 'mode' => 'improve'])
        ->assertRedirect(route('login'));
});

it('forbids every action to authors', function (string $action) {
    Http::fake();

    $this->actingAs(User::factory()->author()->create())
        ->postJson(route("admin.ai-assistant.actions.{$action}"), [])
        ->assertForbidden();

    Http::assertNothingSent();
})->with(['outline', 'rewrite', 'excerpt', 'seo', 'alt-text', 'translate']);

it('sends nothing when the plugin is disabled', function () {
    Http::fake();
    app(PluginManager::class)->disable('ai-assistant');

    shorten(User::factory()->editor()->create())
        ->assertStatus(503)
        ->assertJsonPath('message', 'The AI assistant plugin is not enabled.');

    Http::assertNothingSent();
});

it('sends nothing when the API key is empty', function () {
    Http::fake();
    app(AiSettings::class)->forgetApiKey();

    shorten(User::factory()->editor()->create())
        ->assertStatus(503)
        ->assertJsonPath('message', 'Add an API key on the AI assistant settings page first.');

    Http::assertNothingSent();
});

it('sends nothing when the saved key cannot be decrypted', function () {
    Http::fake();
    app(Settings::class)->set(AiSettings::API_KEY, 'not-an-encrypted-value');

    shorten(User::factory()->editor()->create())->assertStatus(503);

    Http::assertNothingSent();
});

it('sends no image when the plugin is disabled', function () {
    Http::fake();
    app(PluginManager::class)->disable('ai-assistant');
    $media = Media::factory()->create(['size' => 2048]);

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.ai-assistant.actions.alt-text'), ['media_id' => $media->id])
        ->assertStatus(503);

    Http::assertNothingSent();
});

it('stops a user at the rate limit from the settings', function () {
    app(AiSettings::class)->save(['model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 2]);
    Ai::fake(Ai::reply('Ferries leave every 20 minutes.'));
    $editor = User::factory()->editor()->create();

    shorten($editor)->assertOk();
    shorten($editor)->assertOk();

    shorten($editor)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', 'You have used all your AI requests for now. Try again in 60 min.');

    Http::assertSentCount(2);
    expect(AiUsage::query()->sole()->requests)->toBe(2);
});

it('counts the rate limit per user', function () {
    app(AiSettings::class)->save(['model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 1]);
    Ai::fake(Ai::reply('Ferries leave every 20 minutes.'));

    shorten(User::factory()->editor()->create())->assertOk();
    shorten(User::factory()->editor()->create())->assertOk();

    Http::assertSentCount(2);
});

it('opens the limit again after the window', function () {
    config(['ai-assistant.limits.rate_limit_window' => 600]);
    app(AiSettings::class)->save(['model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 1]);
    Ai::fake(Ai::reply('Ferries leave every 20 minutes.'));
    $editor = User::factory()->editor()->create();

    shorten($editor)->assertOk();
    shorten($editor)->assertStatus(429)->assertJsonPath('message', 'You have used all your AI requests for now. Try again in 10 min.');

    $this->travel(601)->seconds();

    shorten($editor)->assertOk();
});

it('counts failed provider calls against the limit', function () {
    config(['ai-assistant.http.retries' => 0]);
    app(AiSettings::class)->save(['model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 1]);
    Ai::fake(Ai::error('api_error', 'Internal server error'), 500);
    $editor = User::factory()->editor()->create();

    shorten($editor)->assertStatus(502);
    shorten($editor)->assertStatus(429);

    Http::assertSentCount(1);
});

it('does not count requests that fail validation', function () {
    app(AiSettings::class)->save(['model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 1]);
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => '', 'mode' => 'shorten'])->assertUnprocessable();

    expect(app(AiGuard::class)->remaining($editor))->toBe(1);
});
