<?php

namespace Verso\Plugins\AiAssistant\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;
use Verso\Plugins\AiAssistant\AiRequest;
use Verso\Plugins\AiAssistant\AiResponse;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Contracts\AiProvider;
use Verso\Plugins\AiAssistant\Exceptions\AiProviderError;
use Verso\Plugins\AiAssistant\Exceptions\AiUnavailable;

// Calls the Anthropic Messages API with Laravel Http (no SDK).
class AnthropicProvider implements AiProvider
{
    // Answers that are worth a new try.
    protected const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    /**
     * @param  array{url: string, version: string, effort: string, fallbacks: string, fallbacks_beta: string, fallback_models: list<string>}  $api
     * @param  array{timeout: int, connect_timeout: int, retries: int, retry_delay: int}  $http
     */
    public function __construct(
        protected Http $client,
        protected AiSettings $settings,
        protected array $api,
        protected array $http,
    ) {}

    public function name(): string
    {
        return 'Anthropic';
    }

    public function send(AiRequest $request): AiResponse
    {
        $key = $this->settings->apiKey();

        if (trim($key) === '') {
            throw AiUnavailable::missingKey();
        }

        try {
            $response = $this->client
                ->withHeaders($this->headers($key))
                ->acceptJson()
                ->asJson()
                ->timeout(max(1, $this->http['timeout']))
                ->connectTimeout(max(1, $this->http['connect_timeout']))
                ->retry(
                    max(1, $this->http['retries'] + 1),
                    max(0, $this->http['retry_delay']),
                    fn (Throwable $exception): bool => $this->shouldRetry($exception),
                    throw: false,
                )
                ->post($this->api['url'], $this->body($request));
        } catch (ConnectionException $exception) {
            throw str_contains(strtolower($exception->getMessage()), 'timed out')
                ? AiProviderError::timeout()
                : AiProviderError::unreachable();
        }

        if ($response->failed()) {
            throw $this->errorFor($response);
        }

        return $this->answer($response);
    }

    /**
     * The JSON body of the Messages API request.
     *
     * @return array<string, mixed>
     */
    public function body(AiRequest $request): array
    {
        $body = [
            'model' => $this->settings->model(),
            'max_tokens' => $this->settings->maxTokens(),
            'system' => $request->system,
            'messages' => [
                ['role' => 'user', 'content' => $request->content],
            ],
        ];

        $outputConfig = [];

        if ($this->api['effort'] !== '') {
            $outputConfig['effort'] = $this->api['effort'];
        }

        if ($request->schema !== null) {
            $outputConfig['format'] = ['type' => 'json_schema', 'schema' => $request->schema];
        }

        if ($outputConfig !== []) {
            $body['output_config'] = $outputConfig;
        }

        if ($this->usesFallbacks()) {
            $body['fallbacks'] = $this->api['fallbacks'];
        }

        return $body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $key): array
    {
        $headers = [
            'x-api-key' => $key,
            'anthropic-version' => $this->api['version'],
        ];

        if ($this->usesFallbacks()) {
            $headers['anthropic-beta'] = $this->api['fallbacks_beta'];
        }

        return $headers;
    }

    // Server side fallbacks are sent only to models that accept them.
    protected function usesFallbacks(): bool
    {
        return $this->api['fallbacks'] !== ''
            && $this->api['fallbacks_beta'] !== ''
            && in_array($this->settings->model(), $this->api['fallback_models'], true);
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && in_array($exception->response->status(), self::RETRY_STATUSES, true);
    }

    protected function errorFor(Response $response): AiProviderError
    {
        $message = $response->json('error.message');

        return match (true) {
            in_array($response->status(), [401, 403], true) => AiProviderError::rejectedKey(),
            in_array($response->status(), [429, 529], true) => AiProviderError::busy(),
            $response->status() === 408 => AiProviderError::timeout(),
            $response->status() === 400 && is_string($message) && $message !== '' => AiProviderError::invalidRequest($message),
            default => AiProviderError::unreachable(),
        };
    }

    protected function answer(Response $response): AiResponse
    {
        $data = $response->json();

        if (! is_array($data) || ! is_array($data['content'] ?? null)) {
            throw AiProviderError::badAnswer();
        }

        $stopReason = (string) ($data['stop_reason'] ?? '');

        if ($stopReason === 'refusal') {
            throw AiProviderError::refused();
        }

        if ($stopReason === 'max_tokens') {
            throw AiProviderError::cutOff();
        }

        $text = '';

        foreach ($data['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        if (trim($text) === '') {
            throw AiProviderError::badAnswer();
        }

        /** @var array<string, mixed> $usage */
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return new AiResponse(
            text: trim($text),
            inputTokens: (int) ($usage['input_tokens'] ?? 0)
                + (int) ($usage['cache_creation_input_tokens'] ?? 0)
                + (int) ($usage['cache_read_input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            model: (string) ($data['model'] ?? ''),
            stopReason: $stopReason,
        );
    }
}
