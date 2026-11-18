<?php

namespace Tests\Support;

use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\PluginManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Http;
use Verso\Plugins\AiAssistant\AiSettings;

// Turns on the AI Assistant plugin for a test and builds fake Anthropic answers.
class AiAssistantFixtures
{
    public const SLUG = 'ai-assistant';

    public const API_KEY = 'sk-ant-api03-harbor-lights-7F2kQ9xZ';

    public const API_URL = 'https://api.anthropic.com/v1/messages';

    // Enable and boot the plugin, with no wait between retries.
    public static function enable(bool $withKey = true): PluginManager
    {
        $manager = PluginFixtures::use(base_path('plugins'));

        app(EnablePlugin::class)->handle(self::SLUG);
        $manager->register($manager->get(self::SLUG));

        app(Router::class)->getRoutes()->refreshNameLookups();
        app(Router::class)->getRoutes()->refreshActionLookups();

        config(['ai-assistant.http.retry_delay' => 0]);

        if ($withKey) {
            app(AiSettings::class)->save([
                'api_key' => self::API_KEY,
                'model' => 'claude-sonnet-5-5',
                'max_tokens' => 4000,
                'rate_limit' => 30,
            ]);
        }

        return $manager;
    }

    /**
     * A Messages API answer with one text block.
     *
     * @return array<string, mixed>
     */
    public static function reply(string $text, int $input = 180, int $output = 64, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_01HarborLights9Qx',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stopReason,
            'stop_details' => null,
            'usage' => ['input_tokens' => $input, 'output_tokens' => $output],
        ];
    }

    /**
     * A Messages API answer whose text is the given data as JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function jsonReply(array $data, int $input = 240, int $output = 96): array
    {
        return self::reply((string) json_encode($data), $input, $output);
    }

    // Answer every Messages API call with the given body.
    /**
     * @param  array<string, mixed>  $body
     */
    public static function fake(array $body, int $status = 200): void
    {
        Http::preventStrayRequests();
        Http::fake([self::API_URL => Http::response($body, $status)]);
    }

    /**
     * An Anthropic error body.
     *
     * @return array<string, mixed>
     */
    public static function error(string $type, string $message): array
    {
        return ['type' => 'error', 'error' => ['type' => $type, 'message' => $message]];
    }
}
