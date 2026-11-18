<?php

namespace Verso\Plugins\Forms\Support;

use App\Verso\Core\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

// Forms options: saved settings first, then the plugin config.
class FormsSettings
{
    // Captcha services the site can use. "none" keeps everything on the site.
    public const CAPTCHA_PROVIDERS = ['none', 'turnstile', 'hcaptcha'];

    protected const SECRET_KEY = 'forms.captcha_secret';

    public function __construct(protected Settings $settings) {}

    public function retentionDays(): int
    {
        return max(0, (int) $this->settings->get('forms.retention_days', config('forms.retention_days')));
    }

    public function maxLinks(): int
    {
        return max(0, (int) $this->settings->get('forms.max_links', config('forms.spam.max_links')));
    }

    public function minSeconds(): int
    {
        return max(0, (int) $this->settings->get('forms.min_seconds', config('forms.spam.min_seconds')));
    }

    /**
     * Lower case words and phrases that mark a message as spam.
     *
     * @return list<string>
     */
    public function blockedWords(): array
    {
        $words = $this->settings->get('forms.blocked_words', config('forms.spam.blocked_words'));
        $words = is_array($words) ? $words : preg_split('/[,\n]/', (string) $words);

        return array_values(array_unique(array_filter(array_map(
            fn ($word): string => mb_strtolower(trim((string) $word)),
            $words === false ? [] : $words,
        ))));
    }

    public function captchaProvider(): string
    {
        $provider = (string) $this->settings->get('forms.captcha_provider', 'none');

        return in_array($provider, self::CAPTCHA_PROVIDERS, true) ? $provider : 'none';
    }

    public function captchaSiteKey(): string
    {
        return (string) $this->settings->get('forms.captcha_site_key', '');
    }

    public function captchaSecret(): string
    {
        $stored = (string) $this->settings->get(self::SECRET_KEY, '');

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return '';
        }
    }

    // A captcha runs only when a service is picked and both keys are set.
    public function captchaActive(): bool
    {
        return $this->captchaProvider() !== 'none' && $this->captchaSiteKey() !== '' && $this->captchaSecret() !== '';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        $this->settings->set('forms.retention_days', (int) $values['retention_days']);
        $this->settings->set('forms.max_links', (int) $values['max_links']);
        $this->settings->set('forms.min_seconds', (int) $values['min_seconds']);
        $this->settings->set('forms.blocked_words', implode("\n", array_filter(array_map('trim', preg_split('/[,\n]/', (string) ($values['blocked_words'] ?? '')) ?: []))));
        $this->settings->set('forms.captcha_provider', (string) $values['captcha_provider']);
        $this->settings->set('forms.captcha_site_key', trim((string) ($values['captcha_site_key'] ?? '')));

        // An empty secret keeps the saved one, so it never has to go back to the browser.
        $secret = trim((string) ($values['captcha_secret'] ?? ''));
        if ($secret !== '') {
            $this->settings->set(self::SECRET_KEY, Crypt::encryptString($secret));
        }

        if (($values['captcha_provider'] ?? 'none') === 'none' && ($values['forget_secret'] ?? false)) {
            $this->settings->set(self::SECRET_KEY, '');
        }
    }

    /**
     * Values for the settings page. The secret is never sent, only whether it is set.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'retention_days' => $this->retentionDays(),
            'max_links' => $this->maxLinks(),
            'min_seconds' => $this->minSeconds(),
            'blocked_words' => implode("\n", $this->blockedWords()),
            'captcha_provider' => $this->captchaProvider(),
            'captcha_site_key' => $this->captchaSiteKey(),
            'captcha_secret' => '',
            'has_captcha_secret' => $this->captchaSecret() !== '',
        ];
    }
}
