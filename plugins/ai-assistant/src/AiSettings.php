<?php

namespace Verso\Plugins\AiAssistant;

use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Seo\SeoSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;

// The plugin settings stored in the site settings. The API key is kept encrypted.
class AiSettings
{
    public const API_KEY = 'ai-assistant.api_key';

    public const MODEL = 'ai-assistant.model';

    public const MAX_TOKENS = 'ai-assistant.max_tokens';

    public const RATE_LIMIT = 'ai-assistant.rate_limit';

    // A few lines about the business, used by the page editor helper.
    public const BUSINESS_DESCRIPTION = 'ai-assistant.business_description';

    // Site settings that prefill the business description until someone saves one.
    protected const BUSINESS_FALLBACKS = [SeoSettings::DEFAULT_DESCRIPTION, 'site.tagline'];

    // Characters of the key shown at the end of the masked value.
    protected const VISIBLE_KEY_CHARS = 4;

    public function __construct(
        protected Settings $settings,
        protected Encrypter $encrypter,
    ) {}

    // The plain API key, or an empty string when none is saved or it cannot be read.
    public function apiKey(): string
    {
        $stored = $this->settings->get(self::API_KEY);

        if (! is_string($stored) || $stored === '') {
            return '';
        }

        try {
            return (string) $this->encrypter->decryptString($stored);
        } catch (DecryptException) {
            return '';
        }
    }

    public function hasApiKey(): bool
    {
        return trim($this->apiKey()) !== '';
    }

    // The key with only its last characters visible, for the settings page.
    public function maskedApiKey(): ?string
    {
        $key = $this->apiKey();

        if ($key === '') {
            return null;
        }

        return str_repeat('•', 8).substr($key, -self::VISIBLE_KEY_CHARS);
    }

    public function model(): string
    {
        $model = $this->settings->get(self::MODEL);

        return is_string($model) && $model !== '' ? $model : (string) config('ai-assistant.anthropic.default_model');
    }

    public function maxTokens(): int
    {
        return $this->positiveInt(self::MAX_TOKENS, (int) config('ai-assistant.defaults.max_tokens'));
    }

    // Requests one user can make in each rate limit window.
    public function rateLimit(): int
    {
        return $this->positiveInt(self::RATE_LIMIT, (int) config('ai-assistant.defaults.rate_limit'));
    }

    /**
     * Save the settings. A null or empty API key keeps the saved key.
     *
     * @param  array{api_key?: string|null, model: string, max_tokens: int, rate_limit: int}  $values
     */
    public function save(array $values): void
    {
        $changes = [
            self::MODEL => $values['model'],
            self::MAX_TOKENS => $values['max_tokens'],
            self::RATE_LIMIT => $values['rate_limit'],
        ];

        $key = trim((string) ($values['api_key'] ?? ''));

        if ($key !== '') {
            $changes[self::API_KEY] = $this->encrypter->encryptString($key);
        }

        $this->settings->setMany($changes);
    }

    // The saved business description, or the site description or tagline when none is saved yet.
    public function businessDescription(): string
    {
        foreach ([self::BUSINESS_DESCRIPTION, ...self::BUSINESS_FALLBACKS] as $key) {
            $value = $this->settings->get($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * The site name and tagline, as context for the page editor helper.
     *
     * @return array{name: string, tagline: string}
     */
    public function site(): array
    {
        return [
            'name' => trim((string) ($this->settings->get('site.name') ?? config('app.name'))),
            'tagline' => trim((string) ($this->settings->get('site.tagline') ?? '')),
        ];
    }

    public function saveBusinessDescription(string $description): void
    {
        $this->settings->setMany([self::BUSINESS_DESCRIPTION => trim($description)]);
    }

    public function forgetApiKey(): void
    {
        $this->settings->forget(self::API_KEY);
    }

    protected function positiveInt(string $key, int $default): int
    {
        $value = $this->settings->get($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
