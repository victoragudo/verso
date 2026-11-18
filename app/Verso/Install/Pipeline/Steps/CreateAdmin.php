<?php

namespace App\Verso\Install\Pipeline\Steps;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\I18n\Locales;
use App\Verso\Install\Pipeline\Contracts\InstallStep;
use App\Verso\Install\Plan\InstallPlan;

// Creates the first admin account, or updates it when a retry finds it.
class CreateAdmin implements InstallStep
{
    public function key(): string
    {
        return 'admin';
    }

    public function label(): string
    {
        return __('Create the admin account');
    }

    public function shouldRun(InstallPlan $plan): bool
    {
        return true;
    }

    public function handle(InstallPlan $plan): void
    {
        $admin = User::query()->firstOrNew(['email' => $plan->admin->email]);
        $admin->fill(['name' => $plan->admin->name, 'password' => $plan->admin->password]);
        $admin->forceFill([
            'role' => Role::Admin,
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'admin_locale' => $this->adminLocale($plan),
        ])->save();
    }

    // The installer language becomes the admin language, unless it is the same as the site language.
    protected function adminLocale(InstallPlan $plan): ?string
    {
        $locales = app(Locales::class);
        $language = $locales->normalize($plan->language);

        return $language !== null && $language !== $locales->normalize($plan->site->locale) ? $language : null;
    }
}
