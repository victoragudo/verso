<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Enums\Capability;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AiAssistantFixtures;
use Tests\Support\PluginFixtures;
use Verso\Plugins\AiAssistant\Contracts\AiProvider;
use Verso\Plugins\AiAssistant\Providers\AnthropicProvider;

it('ships a valid manifest that asks for the ai.use capability', function () {
    $plugin = PluginFixtures::use(base_path('plugins'))->get('ai-assistant');

    expect($plugin->canBeEnabled())->toBeTrue()
        ->and($plugin->manifest->name)->toBe('AI Assistant')
        ->and($plugin->manifest->permissions)->toBe([Capability::UseAi]);
});

it('is off by default', function () {
    $manager = PluginFixtures::use(base_path('plugins'));

    expect($manager->isEnabled('ai-assistant'))->toBeFalse()
        ->and(Route::has('admin.ai-assistant.index'))->toBeFalse();
});

it('creates the usage table when it is enabled', function () {
    PluginFixtures::withoutTables('ai-assistant');

    expect(Schema::hasTable('ai_usage'))->toBeFalse();

    AiAssistantFixtures::enable();

    expect(Schema::hasTable('ai_usage'))->toBeTrue()
        ->and(Schema::hasColumns('ai_usage', ['user_id', 'day', 'requests', 'input_tokens', 'output_tokens']))->toBeTrue();
});

it('registers its routes, config and migrations when enabled', function () {
    AiAssistantFixtures::enable();

    expect(Route::has('admin.ai-assistant.index'))->toBeTrue()
        ->and(Route::has('admin.ai-assistant.actions.outline'))->toBeTrue()
        ->and(Route::has('admin.ai-assistant.actions.translate'))->toBeTrue()
        ->and(config('ai-assistant.anthropic.default_model'))->toBe('claude-sonnet-5-5')
        ->and(config('ai-assistant.anthropic.version'))->toBe('2026-10-21')
        ->and(app(Migrator::class)->paths())->toContain(base_path('plugins/ai-assistant/database/migrations'));
});

it('binds the Anthropic provider to the provider contract', function () {
    AiAssistantFixtures::enable();

    expect(app(AiProvider::class))->toBeInstanceOf(AnthropicProvider::class)
        ->and(app(AiProvider::class)->name())->toBe('Anthropic');
});

it('adds a settings nav item for admins only', function () {
    AiAssistantFixtures::enable();

    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();

    $keys = fn ($user): array => array_column(AdminNav::forUser($user), 'key');

    expect($keys($admin))->toContain('ai-assistant.settings')
        ->and($keys($editor))->not->toContain('ai-assistant.settings')
        ->and(collect(AdminNav::forUser($admin))->firstWhere('key', 'ai-assistant.settings'))->toMatchArray(['group' => 'settings', 'parent' => null, 'label' => 'AI Assistant']);
});
