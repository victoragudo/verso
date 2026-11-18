<?php

namespace Verso\Plugins\Forms\Spam;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Verso\Plugins\Forms\Support\FormsSettings;

// Optional check with Cloudflare Turnstile or hCaptcha, only when the admin saved keys.
class CaptchaVerifier
{
    public function __construct(protected FormsSettings $settings) {}

    public function active(): bool
    {
        return $this->settings->captchaActive();
    }

    /**
     * What the public form needs to show the widget, or null when no captcha is used.
     *
     * @return array{provider: string, script: string, site_key: string, widget_class: string}|null
     */
    public function widget(): ?array
    {
        if (! $this->active()) {
            return null;
        }

        $provider = $this->settings->captchaProvider();

        return [
            'provider' => $provider,
            'script' => (string) config("forms.captcha.{$provider}.script"),
            'site_key' => $this->settings->captchaSiteKey(),
            'widget_class' => (string) config("forms.captcha.{$provider}.widget_class"),
        ];
    }

    // True when no captcha is used or the service accepts the answer.
    public function passes(Request $request): bool
    {
        if (! $this->active()) {
            return true;
        }

        $provider = $this->settings->captchaProvider();
        $answer = $request->input((string) config("forms.captcha.{$provider}.response_field"));

        if (! is_string($answer) || $answer === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(max(1, (int) config('forms.captcha.timeout')))
                ->post((string) config("forms.captcha.{$provider}.verify_url"), [
                    'secret' => $this->settings->captchaSecret(),
                    'response' => $answer,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
