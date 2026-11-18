<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Verification\VerificationReport;
use App\Verso\Extensions\Verification\VerificationSettings;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;
use Tests\Support\ThemeFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.extensions.verification.sandbox.enabled' => false]);
    $this->admin = User::factory()->admin()->create();

    // Upload a theme zip as the admin and return the staging token.
    $this->upload = function (PackageZip $zip): string {
        $file = new UploadedFile($zip->build($this->sandbox->zipPath(uniqid())), 'theme.zip', 'application/zip', null, true);
        $response = $this->actingAs($this->admin)->post(route('admin.extensions.upload'), ['kind' => 'theme', 'package' => $file]);

        return basename((string) $response->headers->get('Location'));
    };
});

afterEach(function () {
    ThemeFixtures::cleanup();
    $this->sandbox->destroy();
});

it('verifies an uploaded theme before the consent screen and shows the report', function () {
    $token = ($this->upload)(ThemeFixtures::zip(ThemeFixtures::withScreenshot('harbor-light')));

    $verification = PluginVerification::query()->sole();

    expect($verification->kind)->toBe(ExtensionKind::Theme)
        ->and($verification->slug)->toBe('harbor-light')
        ->and($verification->staging_token)->toBe($token)
        ->and($verification->risk->value)->toBe('low')
        ->and($verification->blockingRules())->toBe([]);

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.review', $token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/extensions/review')
            ->where('staged.package.kind', 'theme')
            ->where('staged.package.requires', '^0.1')
            ->where('verification.kind', 'theme')
            ->where('verification.risk', 'low')
            ->where('verification.blocking', [])
            ->where('verification.details.theme.screenshot', 'screenshot.png')
            ->where('verification.details.sandbox.status', 'skipped'));
});

it('installs a clean theme and marks its report as the installed one', function () {
    $token = ($this->upload)(ThemeFixtures::zip(ThemeFixtures::withScreenshot('harbor-light')));

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token))
        ->assertRedirect(route('admin.themes.index'));

    expect(app(ThemeManager::class)->find('harbor-light'))->not->toBeNull()
        ->and(PluginVerification::latestFor('harbor-light', ExtensionKind::Theme)?->installed_at)->not->toBeNull();
});

it('never installs a theme with missing required views, even with the slug typed', function () {
    $token = ($this->upload)(ThemeFixtures::zip('half-built'));

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token), ['confirm_slug' => 'half-built'])
        ->assertSessionHasErrors(['confirm_slug' => 'This theme cannot be installed: theme.missing-view. Fix the findings and upload it again.']);

    expect(app(ThemeManager::class)->find('half-built'))->toBeNull();
});

it('never installs a child theme whose parent is missing', function () {
    $token = ($this->upload)(ThemeFixtures::zip('orphan-tide'));

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token), ['confirm_slug' => 'orphan-tide'])
        ->assertSessionHasErrors('confirm_slug');

    expect(app(ThemeManager::class)->find('orphan-tide'))->toBeNull();
});

it('asks for the slug before it installs a high risk theme', function () {
    $token = ($this->upload)(ThemeFixtures::zip('driftwood')->remove('driftwood/src/Helpers.php'));

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token))
        ->assertSessionHasErrors(['confirm_slug' => 'This theme is high risk. Type "driftwood" to confirm the install.']);

    expect(app(ThemeManager::class)->find('driftwood'))->toBeNull();
});

it('refuses a theme zip with PHP files outside its views before any check', function () {
    $file = new UploadedFile(ThemeFixtures::zip('driftwood')->build($this->sandbox->zipPath('driftwood')), 'driftwood.zip', 'application/zip', null, true);

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.upload'), ['kind' => 'theme', 'package' => $file])
        ->assertSessionHasErrors('package');

    expect(PluginVerification::query()->count())->toBe(0);
});

it('installs a high risk theme after the slug is typed, even when high risk plugins are blocked', function () {
    app(VerificationSettings::class)->setBlocksHighRisk(true);
    $token = ($this->upload)(ThemeFixtures::zip('driftwood')->remove('driftwood/src/Helpers.php'));

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token), ['confirm_slug' => 'driftwood'])
        ->assertRedirect(route('admin.themes.index'));

    expect(app(ThemeManager::class)->find('driftwood'))->not->toBeNull();
});

it('keeps theme and plugin reports with the same slug apart', function () {
    PluginVerification::record(VerificationReport::make('harbor-light', '0.9.0', []), 'installed');
    ($this->upload)(ThemeFixtures::zip(ThemeFixtures::withScreenshot('harbor-light')));

    expect(PluginVerification::latestFor('harbor-light')?->version)->toBe('0.9.0')
        ->and(PluginVerification::latestFor('harbor-light', ExtensionKind::Theme)?->version)->toBe('1.2.0')
        ->and(PluginVerification::latestRisks(ExtensionKind::Theme))->toBe(['harbor-light' => 'low']);
});

it('shows a theme with its newest report on the theme detail page', function () {
    $this->actingAs($this->admin)->post(route('admin.themes.verify', 'default'))
        ->assertRedirect(route('admin.themes.show', 'default'));

    $this->actingAs($this->admin)
        ->get(route('admin.themes.show', 'default'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/theme/show')
            ->where('theme.slug', 'default')
            ->where('theme.active', true)
            ->where('theme.missingViews', [])
            ->where('theme.missingParent', null)
            ->where('verification.kind', 'theme')
            ->where('verification.source', 'installed')
            ->where('verification.blocking', [])
            ->where('versoVersion', config('verso.version')));

    expect(PluginVerification::latestFor('default', ExtensionKind::Theme)?->installed_at)->not->toBeNull();
});

it('shows a theme that was never checked without a report', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.themes.show', 'default'))
        ->assertInertia(fn (Assert $page) => $page->where('verification', null));
});

it('returns 404 for an unknown theme', function () {
    $this->actingAs($this->admin)->get(route('admin.themes.show', 'seaside-base'))->assertNotFound();
    $this->actingAs($this->admin)->post(route('admin.themes.verify', 'seaside-base'))->assertNotFound();
});

it('keeps the theme detail page and checks for people who manage themes', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.themes.show', 'default'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.themes.verify', 'default'))->assertForbidden();
});

it('shows the risk of each theme on the themes pages', function () {
    $this->actingAs($this->admin)->post(route('admin.themes.verify', 'default'));
    app(Settings::class)->set('theme.active', 'default');

    $this->actingAs($this->admin)
        ->get(route('admin.themes.index'))
        ->assertInertia(fn (Assert $page) => $page->where('themes.0.risk', 'low'));

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index', ['tab' => 'themes']))
        ->assertInertia(fn (Assert $page) => $page->where('themes.0.risk', 'low'));
});
