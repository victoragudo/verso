<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Models\PluginVerification;
use App\Verso\Extensions\Verification\VerificationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;
use Tests\Support\VerificationFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.plugins.composer_installed' => null, 'verso.extensions.verification.sandbox.enabled' => false]);
    $this->admin = User::factory()->admin()->create();

    // Upload a zip as the admin and return the staging token.
    $this->upload = function (PackageZip $zip): string {
        $file = new UploadedFile($zip->build($this->sandbox->zipPath(uniqid())), 'plugin.zip', 'application/zip', null, true);
        $response = $this->actingAs($this->admin)->post(route('admin.extensions.upload'), ['kind' => 'plugin', 'package' => $file]);

        return basename((string) $response->headers->get('Location'));
    };
});

afterEach(function () {
    VerificationFixtures::cleanup();
    $this->sandbox->destroy();
});

it('verifies an upload before the consent screen and shows the report', function () {
    $token = ($this->upload)(VerificationFixtures::zip('harbor-tides'));

    $verification = PluginVerification::query()->sole();

    expect($verification->staging_token)->toBe($token)
        ->and($verification->slug)->toBe('harbor-tides')
        ->and($verification->source)->toBe('upload')
        ->and($verification->user_id)->toBe($this->admin->id)
        ->and($verification->risk->value)->toBe('low');

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.review', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/extensions/review')
            ->where('verification.risk', 'low')
            ->where('verification.slug', 'harbor-tides')
            ->where('verification.findings.0.rule', 'sandbox.skipped')
            ->where('verification.details.files', 5)
            ->where('blockHighRisk', false)
            ->where('staged.package.network', [])
        );

    expect(PluginVerification::query()->count())->toBe(1);
});

it('installs a low risk plugin without a slug and keeps the report', function () {
    $token = ($this->upload)(VerificationFixtures::zip('harbor-tides'));

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token))
        ->assertRedirect(route('admin.plugins.index'));

    $verification = PluginVerification::query()->sole();

    expect(File::isDirectory($this->sandbox->pluginsPath().'/harbor-tides'))->toBeTrue()
        ->and($verification->installed_at)->not->toBeNull()
        ->and($verification->staging_token)->toBeNull();
});

it('asks for the slug before installing a high risk plugin', function () {
    $token = ($this->upload)(VerificationFixtures::zip('backup-runner'));

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.review', $token))
        ->assertInertia(fn (Assert $page) => $page->where('verification.risk', 'high')->where('verification.findings.0.rule', 'php.shell'));

    $this->actingAs($this->admin)
        ->from(route('admin.extensions.review', $token))
        ->post(route('admin.extensions.install', $token), ['confirm_slug' => 'backup'])
        ->assertRedirect(route('admin.extensions.review', $token))
        ->assertSessionHasErrors(['confirm_slug' => 'This plugin is high risk. Type "backup-runner" to confirm the install.']);

    expect(File::exists($this->sandbox->pluginsPath().'/backup-runner'))->toBeFalse();

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $token), ['confirm_slug' => 'backup-runner'])
        ->assertRedirect(route('admin.plugins.index'));

    expect(File::isDirectory($this->sandbox->pluginsPath().'/backup-runner'))->toBeTrue();
});

it('refuses high risk plugins when the site blocks them', function () {
    app(VerificationSettings::class)->setBlocksHighRisk(true);
    $token = ($this->upload)(VerificationFixtures::zip('quiet-optimizer'));

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.review', $token))
        ->assertInertia(fn (Assert $page) => $page->where('blockHighRisk', true)->where('verification.risk', 'high'));

    $this->actingAs($this->admin)
        ->from(route('admin.extensions.review', $token))
        ->post(route('admin.extensions.install', $token), ['confirm_slug' => 'quiet-optimizer'])
        ->assertSessionHasErrors(['confirm_slug' => 'This plugin is high risk, and this site blocks high risk plugins.']);

    expect(File::exists($this->sandbox->pluginsPath().'/quiet-optimizer'))->toBeFalse();
});

it('verifies again when the staged zip changed', function () {
    $token = ($this->upload)(VerificationFixtures::zip('harbor-tides'));
    $zip = $this->sandbox->root.'/storage/staging/'.$token.'/package.zip';
    VerificationFixtures::zip('harbor-tides')->add('harbor-tides/src/Shell.php', "<?php\n\nexec('id');\n")->build($zip);

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.review', $token))
        ->assertInertia(fn (Assert $page) => $page->where('verification.risk', 'high'));

    expect(PluginVerification::query()->count())->toBe(2);
});

it('runs the same checks on registry packages', function () {
    $zip = File::get(VerificationFixtures::zip('weather-widget')->build($this->sandbox->zipPath('weather-widget')));
    config(['verso.extensions.registry.url' => 'https://registry.verso.example/index.json']);
    Http::fake([
        'registry.verso.example/index.json' => Http::response(['extensions' => [[
            'type' => 'plugin',
            'name' => 'Weather Widget',
            'slug' => 'weather-widget',
            'version' => '1.2.0',
            'description' => 'Shows the local weather forecast in a sidebar block.',
            'download_url' => 'https://registry.verso.example/packages/weather-widget-1.2.0.zip',
            'sha256' => hash('sha256', $zip),
        ]]]),
        'registry.verso.example/packages/weather-widget-1.2.0.zip' => Http::response($zip),
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.extensions.registry', ['plugin', 'weather-widget']));
    $token = basename((string) $response->headers->get('Location'));

    $verification = PluginVerification::query()->sole();

    expect($verification->source)->toBe('registry')
        ->and($verification->staging_token)->toBe($token)
        ->and($verification->risk->value)->toBe('high')
        ->and(array_column($verification->findings, 'rule'))->toContain('permission.undeclared-network');
});

it('shows the plugin check settings on the Add new tab', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index', ['tab' => 'add']))
        ->assertInertia(fn (Assert $page) => $page->where('add.verification', ['blockHighRisk' => false, 'canChange' => true, 'sandbox' => false]));
});

it('lets admins block high risk plugins', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.extensions.settings'), ['block_high_risk' => true])
        ->assertRedirect();

    expect(app(Settings::class)->get('extensions.block_high_risk'))->toBeTrue()
        ->and(app(VerificationSettings::class)->blocksHighRisk())->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.extensions.settings'), ['block_high_risk' => false]);

    expect(app(VerificationSettings::class)->blocksHighRisk())->toBeFalse();
});

it('reads the default of the block setting from config', function () {
    config(['verso.extensions.verification.block_high_risk' => true]);

    expect(app(VerificationSettings::class)->blocksHighRisk())->toBeTrue();
});

it('keeps people without settings rights away from the block setting', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->put(route('admin.extensions.settings'), ['block_high_risk' => true])->assertForbidden();
    $this->actingAs($this->admin)->put(route('admin.extensions.settings'), [])->assertSessionHasErrors('block_high_risk');

    expect(app(VerificationSettings::class)->blocksHighRisk())->toBeFalse();
});
