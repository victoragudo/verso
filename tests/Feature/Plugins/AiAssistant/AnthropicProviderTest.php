<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\AiAssistantFixtures as Ai;
use Verso\Plugins\AiAssistant\AiRequest;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Contracts\AiProvider;
use Verso\Plugins\AiAssistant\Exceptions\AiProviderError;
use Verso\Plugins\AiAssistant\Exceptions\AiUnavailable;

beforeEach(function () {
    Ai::enable();
    $this->provider = app(AiProvider::class);
});

it('posts to the Messages API with the key, version and json headers', function () {
    Ai::fake(Ai::reply('A calmer harbor at dawn.'));

    $this->provider->send(AiRequest::text('You are a careful copy editor.', 'The harbor was very very quiet at dawn.'));

    Http::assertSent(function (Request $request): bool {
        return $request->url() === Ai::API_URL
            && $request->method() === 'POST'
            && $request->hasHeader('x-api-key', Ai::API_KEY)
            && $request->hasHeader('anthropic-version', '2026-10-21')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('Accept', 'application/json');
    });
});

it('sends the model, max tokens, system prompt and one user message', function () {
    Ai::fake(Ai::reply('A calmer harbor at dawn.'));

    $this->provider->send(AiRequest::text('You are a careful copy editor.', 'The harbor was very very quiet at dawn.'));

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $body['model'] === 'claude-sonnet-5-5'
            && $body['max_tokens'] === 4000
            && $body['system'] === 'You are a careful copy editor.'
            && $body['messages'] === [[
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => 'The harbor was very very quiet at dawn.']],
            ]]
            && $body['output_config'] === ['effort' => 'low']
            && ! array_key_exists('temperature', $body)
            && ! array_key_exists('thinking', $body);
    });
});

it('asks for a json schema answer through output_config.format', function () {
    Ai::fake(Ai::jsonReply(['title' => 'Lisbon in three days', 'description' => 'Trams, tiles and custard tarts.']));

    $schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title'], 'additionalProperties' => false];

    $this->provider->send(AiRequest::text('You are an SEO editor.', 'Title: Lisbon in three days', $schema));

    Http::assertSent(fn (Request $request): bool => $request['output_config'] === [
        'effort' => 'low',
        'format' => ['type' => 'json_schema', 'schema' => $schema],
    ]);
});

it('leaves effort out when the config is empty', function () {
    config(['ai-assistant.anthropic.effort' => '']);
    Ai::fake(Ai::reply('Done.'));

    app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text to edit.'));

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('output_config', $request->data()));
});

it('turns on server side fallbacks for models that accept them', function () {
    Ai::fake(Ai::reply('Done.'));

    $this->provider->send(AiRequest::text('Edit.', 'Text to edit.'));

    Http::assertSent(fn (Request $request): bool => $request['fallbacks'] === 'default'
        && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01'));
});

it('sends no fallbacks for other models', function () {
    app(AiSettings::class)->save(['model' => 'claude-haiku-5-5', 'max_tokens' => 1200, 'rate_limit' => 30]);
    Ai::fake(Ai::reply('Done.'));

    app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text to edit.'));

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'claude-haiku-5-5'
        && $request['max_tokens'] === 1200
        && ! array_key_exists('fallbacks', $request->data())
        && ! $request->hasHeader('anthropic-beta'));
});

it('sends images as base64 content blocks', function () {
    Ai::fake(Ai::reply('Fishing boats moored in a small harbor at sunset.'));

    $image = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode('png-bytes')]];

    $this->provider->send(new AiRequest('Write alt text.', [$image, ['type' => 'text', 'text' => 'File name: harbor.png']]));

    Http::assertSent(fn (Request $request): bool => $request['messages'][0]['content'][0] === $image);
});

it('reads the text and the tokens of the answer', function () {
    Ai::fake([
        ...Ai::reply('A calmer harbor at dawn.'),
        'usage' => ['input_tokens' => 120, 'cache_creation_input_tokens' => 30, 'cache_read_input_tokens' => 10, 'output_tokens' => 22],
    ]);

    $response = $this->provider->send(AiRequest::text('Edit.', 'The harbor was quiet.'));

    expect($response->text)->toBe('A calmer harbor at dawn.')
        ->and($response->inputTokens)->toBe(160)
        ->and($response->outputTokens)->toBe(22)
        ->and($response->model)->toBe('claude-sonnet-5-5')
        ->and($response->stopReason)->toBe('end_turn');
});

it('joins every text block of the answer', function () {
    Ai::fake([
        ...Ai::reply(''),
        'content' => [
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => 'First half, '],
            ['type' => 'text', 'text' => 'second half.'],
        ],
    ]);

    expect($this->provider->send(AiRequest::text('Edit.', 'Text.'))->text)->toBe('First half, second half.');
});

it('sends nothing when no API key is saved', function () {
    app(AiSettings::class)->forgetApiKey();
    Http::fake();

    expect(fn () => $this->provider->send(AiRequest::text('Edit.', 'Private draft about the merger.')))
        ->toThrow(AiUnavailable::class, 'Add an API key');

    Http::assertNothingSent();
});

it('explains a rejected key', function () {
    Ai::fake(Ai::error('authentication_error', 'invalid x-api-key'), 401);

    expect(fn () => $this->provider->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'rejected the API key');

    Http::assertSentCount(1);
});

it('shows the reason of a bad request', function () {
    Ai::fake(Ai::error('invalid_request_error', 'max_tokens: 900000 > 128000, which is the maximum allowed'), 400);

    try {
        $this->provider->send(AiRequest::text('Edit.', 'Text.'));
        $this->fail('No exception was thrown.');
    } catch (AiProviderError $exception) {
        expect($exception->getMessage())->toContain('max_tokens: 900000 > 128000')
            ->and($exception->status())->toBe(422);
    }
});

it('retries when the API is overloaded and then gives up', function () {
    config(['ai-assistant.http.retries' => 2]);
    Ai::fake(Ai::error('overloaded_error', 'Overloaded'), 529);

    expect(fn () => app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'busy');

    Http::assertSentCount(3);
});

it('retries a server error and returns the next good answer', function () {
    config(['ai-assistant.http.retries' => 1]);
    Http::preventStrayRequests();
    Http::fake([Ai::API_URL => Http::sequence()
        ->push(Ai::error('api_error', 'Internal server error'), 500)
        ->push(Ai::reply('A calmer harbor at dawn.'))]);

    $response = app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.'));

    expect($response->text)->toBe('A calmer harbor at dawn.');
    Http::assertSentCount(2);
});

it('does not retry when retries are set to zero', function () {
    config(['ai-assistant.http.retries' => 0]);
    Ai::fake(Ai::error('rate_limit_error', 'Number of request tokens has exceeded your per-minute rate limit'), 429);

    expect(fn () => app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'busy');

    Http::assertSentCount(1);
});

it('uses the timeout from config', function () {
    config(['ai-assistant.http.timeout' => 42, 'ai-assistant.http.connect_timeout' => 7]);

    $options = [];
    Http::fake(function (Request $request, array $requestOptions) use (&$options) {
        $options = $requestOptions;

        return Http::response(Ai::reply('Done.'));
    });

    app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.'));

    expect($options['timeout'])->toBe(42)
        ->and($options['connect_timeout'])->toBe(7);
});

it('reports a timeout when the provider does not answer in time', function () {
    config(['ai-assistant.http.retries' => 1]);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds'));

    try {
        app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.'));
        $this->fail('No exception was thrown.');
    } catch (AiProviderError $exception) {
        expect($exception->getMessage())->toContain('took too long')
            ->and($exception->status())->toBe(504);
    }
});

it('reports a provider that cannot be reached', function () {
    config(['ai-assistant.http.retries' => 0]);
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host: api.anthropic.com'));

    expect(fn () => app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'could not reach');
});

it('reports a refusal', function () {
    Ai::fake([...Ai::reply(''), 'content' => [], 'stop_reason' => 'refusal']);

    expect(fn () => $this->provider->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'declined');
});

it('reports an answer cut by max tokens', function () {
    Ai::fake(Ai::reply('{"title": "Lisbon in', 300, 4000, 'max_tokens'));

    expect(fn () => $this->provider->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'cut short');
});

it('reports an answer without text', function () {
    Ai::fake(['id' => 'msg_01', 'type' => 'message', 'content' => [], 'stop_reason' => 'end_turn']);

    expect(fn () => $this->provider->send(AiRequest::text('Edit.', 'Text.')))
        ->toThrow(AiProviderError::class, 'could not read');
});

it('posts to the endpoint from config', function () {
    config(['ai-assistant.anthropic.url' => 'https://claude-gateway.lighthouse-media.example/v1/messages']);
    Http::fake(['https://claude-gateway.lighthouse-media.example/*' => Http::response(Ai::reply('Done.'))]);

    app(AiProvider::class)->send(AiRequest::text('Edit.', 'Text.'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://claude-gateway.lighthouse-media.example/v1/messages');
});
