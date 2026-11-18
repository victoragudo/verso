<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\System\Config\ConfigOverlay;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Models\ConfigChange;
use App\Verso\System\Server\ManagedIniFile;
use App\Verso\System\Server\PhpIni;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePhpIni;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Marta Salgado']);
    $this->app->instance(PhpIni::class, FakePhpIni::sharedHosting());
});

it('lets only users with manage_system open System settings', function () {
    $this->actingAs(User::factory()->editor()->create())->get(route('admin.system.settings.index'))->assertForbidden();
    $this->actingAs(User::factory()->editor()->create())->put(route('admin.system.settings.update'), ['values' => ['verso.media.per_page' => 60]])->assertForbidden();

    expect($this->admin->can('manage_system'))->toBeTrue();
});

it('shows the groups, the settings and the effective upload limit', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.system.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/settings')
            ->where('groups.0.key', 'uploads')
            ->where('groups.0.label', 'Uploads and media')
            ->where('parameters.0.key', 'verso.media.max_upload_kb')
            ->where('parameters.0.value', 10240)
            ->where('parameters.0.unit', 'kb')
            ->where('parameters.0.changed', false)
            ->where('parameters.0.upload.bottleneck', 'post_max_size')
            ->where('parameters.0.upload.limitedByServer', true)
            ->where('server.target.key', 'user_ini')
            ->where('server.recommended.0.name', 'upload_max_filesize')
            ->has('server.snippets', 6)
            ->has('groups', fn (Assert $groups) => $groups->each(fn (Assert $group) => $group->hasAll(['key', 'label', 'description', 'icon', 'plugin'])))
        );
});

it('saves a value, logs who changed it and uses it at once', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.system.settings.index'))
        ->put(route('admin.system.settings.update'), ['values' => ['verso.media.max_upload_kb' => 65536, 'verso.media.per_page' => '60']])
        ->assertRedirect(route('admin.system.settings.index'))
        ->assertInertiaFlash('toast.message', '2 settings saved.');

    expect(app(Settings::class)->get('system.config.verso.media.max_upload_kb'))->toBe(65536)
        ->and(config('verso.media.per_page'))->toBe(60)
        ->and(ConfigChange::query()->where('key', 'verso.media.max_upload_kb')->firstOrFail())
        ->user_id->toBe($this->admin->id)
        ->old_value->toBe(10240)
        ->new_value->toBe(65536);
});

it('rejects values outside the range and unknown keys', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.system.settings.update'), ['values' => ['verso.media.per_page' => 5000, 'app.key' => 'base64:stolen']])
        ->assertSessionHasErrors([
            'values.verso.media.per_page' => 'Use 200 items or less.',
            'values.app.key' => 'This setting does not exist.',
        ]);

    expect(app(Settings::class)->has('system.config.verso.media.per_page'))->toBeFalse();
});

it('refuses settings that an env var sets', function () {
    $overlay = app(ConfigOverlay::class);
    $parameter = app(ConfigRegistry::class)->find('mail.default');

    expect($overlay->isSetByEnvironment($parameter))->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('admin.system.settings.update'), ['values' => ['mail.default' => 'smtp']])
        ->assertSessionHasErrors(['values.mail.default' => 'The server environment sets this value, so it cannot be changed here.']);
});

it('runs the apply actions of a changed setting', function () {
    app(PageCache::class);
    $before = cache()->get('illuminate:queue:restart');

    $this->actingAs($this->admin)
        ->put(route('admin.system.settings.update'), ['values' => ['verso.page_cache.ttl' => 7200, 'verso.importer.job_timeout' => 7200]])
        ->assertInertiaFlash('toast.message', '2 settings saved. Cached pages are cleared. Queue workers restart.');

    expect(cache()->get('illuminate:queue:restart'))->not->toBe($before);
});

it('resets a setting to its default and logs it', function () {
    app(Settings::class)->set('system.config.verso.api.rate_limit', 300);
    config(['verso.api.rate_limit' => 300]);

    $this->actingAs($this->admin)
        ->post(route('admin.system.settings.reset'), ['key' => 'verso.api.rate_limit'])
        ->assertInertiaFlash('toast.message', '"Requests per minute" is back to its default.');

    expect(app(Settings::class)->has('system.config.verso.api.rate_limit'))->toBeFalse()
        ->and(config('verso.api.rate_limit'))->toBe(60)
        ->and(ConfigChange::query()->where('action', 'reset')->value('new_value'))->toBe(60);
});

it('shows saved values that are no longer valid as a problem', function () {
    app(Settings::class)->set('system.config.verso.media.per_page', 9000);
    app(ConfigOverlay::class)->apply(app(ConfigRegistry::class)->all());

    $this->actingAs($this->admin)
        ->get(route('admin.system.settings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('parameters.2.key', 'verso.media.per_page')
            ->where('parameters.2.value', 40)
            ->where('parameters.2.problem', 'Use 200 items or less.'));
});

it('writes the PHP limits to public/.user.ini only after consent, with a backup', function () {
    // The biggest upload setting is the 200 MB WordPress import, so PHP must accept 200 MB files.
    $root = sys_get_temp_dir().'/verso-public-'.Str::random(8);
    File::ensureDirectoryExists($root.'/public');
    file_put_contents($root.'/public/.user.ini', "display_errors = Off\n");
    $this->app->instance(ManagedIniFile::class, new ManagedIniFile($root.'/public', $root.'/backups'));

    $this->actingAs($this->admin)->post(route('admin.system.settings.php-limits'), ['target' => 'user_ini'])->assertSessionHasErrors('confirm');

    $this->actingAs($this->admin)
        ->post(route('admin.system.settings.php-limits'), ['target' => 'user_ini', 'confirm' => true])
        ->assertInertiaFlash('toast.message', 'PHP limits written to public/.user.ini. PHP reads it again within 300 seconds.');

    expect(file_get_contents($root.'/public/.user.ini'))->toContain("display_errors = Off\n")
        ->toContain('upload_max_filesize = 200M')
        ->toContain('post_max_size = 201M')
        ->toContain('memory_limit = 512M')
        ->and(File::files($root.'/backups'))->toHaveCount(1)
        ->and(ConfigChange::query()->where('action', 'ini_write')->firstOrFail()->details['file'])->toBe('public/.user.ini');

    File::deleteDirectory($root);
});

it('adds a group for each enabled plugin with settings', function () {
    PluginFixtures::boot('forms');

    $this->actingAs($this->admin)
        ->get(route('admin.system.settings.index'))
        ->assertInertia(fn (Assert $page) => $page->where('groups', fn ($groups) => collect($groups)->contains(fn (array $group): bool => $group['key'] === 'plugin-forms' && $group['plugin'] === 'forms')));
});
