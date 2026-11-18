<?php

namespace Verso\Plugins\Forms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Forms\Support\FormsSettings;

// Settings shared by all forms: retention, spam checks and the optional captcha.
class SettingsController
{
    public function edit(FormsSettings $settings): Response
    {
        return Inertia::render('admin/plugins/forms/settings', [
            'settings' => $settings->toArray(),
            'providers' => [
                ['value' => 'none', 'label' => __('forms::None (only checks on this site)')],
                ['value' => 'turnstile', 'label' => 'Cloudflare Turnstile'],
                ['value' => 'hcaptcha', 'label' => 'hCaptcha'],
            ],
            'urls' => [
                'update' => route('admin.forms.settings.update'),
                'forms' => route('admin.forms.index'),
            ],
        ]);
    }

    public function update(Request $request, FormsSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'max_links' => ['required', 'integer', 'min:0', 'max:100'],
            'min_seconds' => ['required', 'integer', 'min:0', 'max:120'],
            'blocked_words' => ['nullable', 'string', 'max:5000'],
            'captcha_provider' => ['required', Rule::in(FormsSettings::CAPTCHA_PROVIDERS)],
            'captcha_site_key' => ['nullable', 'string', 'max:200', 'required_unless:captcha_provider,none'],
            'captcha_secret' => ['nullable', 'string', 'max:200'],
            'forget_secret' => ['boolean'],
        ], ['captcha_site_key.required_unless' => __('forms::Add the site key of the captcha service.')]);

        if ($data['captcha_provider'] !== 'none' && trim((string) ($data['captcha_secret'] ?? '')) === '' && $settings->captchaSecret() === '') {
            return back()->withErrors(['captcha_secret' => __('forms::Add the secret key of the captcha service.')]);
        }

        $settings->save($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('forms::Form settings saved.')]);

        return back();
    }
}
