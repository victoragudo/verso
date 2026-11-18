<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\I18n\AdminLocale;
use App\Verso\I18n\Locales;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Inés Navarro']);
});

it('shows the admin in the language of the user', function () {
    $this->admin->forceFill(['admin_locale' => 'de'])->save();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('<html lang="de" dir="ltr"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'de')
            ->where('i18n.dir', 'ltr')
            ->where('i18n.userLocale', 'de')
            ->where('i18n.bundle', fn (string $url) => str_starts_with($url, '/i18n/de/'))
            ->has('i18n.locales', count(config('verso.i18n.admin_locales'))));
});

it('sets dir rtl for Arabic', function () {
    $this->admin->forceFill(['admin_locale' => 'ar'])->save();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page->where('i18n.dir', 'rtl'));
});

it('follows the site language when the user has no choice', function () {
    app(Settings::class)->set('site.locale', 'pt');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'pt-BR')
            ->where('i18n.siteLocale', 'pt-BR')
            ->where('i18n.userLocale', null));
});

it('uses English when the site language has no admin translation', function () {
    app(Settings::class)->set('site.locale', 'nl');

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en')->where('i18n.bundle', null));
});

it('keeps the site language on public pages', function () {
    app(Settings::class)->set('site.locale', 'fr');
    $this->admin->forceFill(['admin_locale' => 'ja'])->save();

    $this->actingAs($this->admin)->get('/')->assertSee('lang="fr"', false);
});

it('uses the account language on profile settings', function () {
    $this->admin->forceFill(['admin_locale' => 'ko'])->save();

    $this->actingAs($this->admin)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'ko'));
});

it('detects the browser language on the login screen', function () {
    $this->withHeader('Accept-Language', 'tr-TR,tr;q=0.9,en;q=0.5')
        ->get(route('login'))
        ->assertSee('<html lang="tr"', false)
        ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'tr'));
});

it('lets a guest pick the language on the login screen', function () {
    $this->withHeader('Accept-Language', 'de')
        ->put(route('verso.locale.update'), ['locale' => 'it'])
        ->assertRedirect();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'it'));
});

it('saves the language of a signed in user', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.dashboard'))
        ->put(route('verso.locale.update'), ['locale' => 'hi'])
        ->assertRedirect(route('admin.dashboard'));

    expect($this->admin->fresh()->admin_locale)->toBe('hi');
});

it('goes back to the site language with an empty choice', function () {
    $this->admin->forceFill(['admin_locale' => 'bn'])->save();

    $this->actingAs($this->admin)->put(route('verso.locale.update'), ['locale' => ''])->assertRedirect();

    expect($this->admin->fresh()->admin_locale)->toBeNull();
});

it('refuses languages that are not enabled', function () {
    config(['verso.i18n.admin_locales' => ['en', 'es']]);
    app()->forgetInstance(Locales::class);

    $this->actingAs($this->admin)->put(route('verso.locale.update'), ['locale' => 'ja'])->assertSessionHasErrors('locale');

    expect($this->admin->fresh()->admin_locale)->toBeNull();
});

it('keeps the language picked on the login screen after signing in', function () {
    $this->put(route('verso.locale.update'), ['locale' => 'ru']);

    $this->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password']);

    expect($this->admin->fresh()->admin_locale)->toBe('ru');
});

it('does not replace a saved language when signing in', function () {
    $this->admin->forceFill(['admin_locale' => 'id'])->save();
    $this->put(route('verso.locale.update'), ['locale' => 'ru']);

    $this->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password']);

    expect($this->admin->fresh()->admin_locale)->toBe('id');
});

it('sends mail to users in their admin language', function () {
    Notification::fake();
    $this->admin->forceFill(['admin_locale' => 'fr'])->save();

    $this->admin->notify(new ResetPassword('reset-token'));

    Notification::assertSentTo($this->admin, ResetPassword::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'fr');
});

it('gives users without a choice the site language for mail', function () {
    app(Settings::class)->set('site.locale', 'es');

    expect($this->admin->preferredLocale())->toBe('es')
        ->and(app(AdminLocale::class)->forUser($this->admin))->toBe('es');
});

it('shares the site content language and direction for the editor canvas', function () {
    app(Settings::class)->set('site.locale', 'ar');
    $this->admin->forceFill(['admin_locale' => 'de'])->save();

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'de')
            ->where('i18n.dir', 'ltr')
            ->where('i18n.contentLang', 'ar')
            ->where('i18n.contentDir', 'rtl'));
});
