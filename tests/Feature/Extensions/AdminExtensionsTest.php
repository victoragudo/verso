<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Plugins\PluginManager;
use App\Verso\Theme\ThemeManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    $this->admin = User::factory()->admin()->create();
    $this->upload = fn (PackageZip $zip, string $name = 'tour-dates.zip') => new UploadedFile($zip->build($this->sandbox->zipPath(uniqid())), $name, 'application/zip', null, true);
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('sends old extensions links to the Plugins and themes page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.extensions.index'))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'add']));

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.index', ['tab' => 'browse']))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'browse']));
});

it('shows the upload tab', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index', ['tab' => 'add']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/index')
            ->where('tab', 'add')
            ->where('add.kinds', ['plugin', 'theme'])
            ->where('add.maxUploadKb', (int) config('verso.extensions.max_upload_kb'))
            ->where('registry', null)
        );
});

it('keeps people without plugin or theme rights out', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->get(route('admin.extensions.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.extensions.upload'), ['kind' => 'plugin', 'package' => ($this->upload)(PackageZip::plugin())])->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.extensions.remove', ['plugin', 'tour-dates']))->assertForbidden();
    auth()->guard()->logout();
    $this->post(route('admin.extensions.upload'))->assertRedirect(route('login'));
});

it('uploads a plugin and opens the consent screen', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.extensions.upload'), [
        'kind' => 'plugin',
        'package' => ($this->upload)(PackageZip::plugin('tour-dates', '1.0.0')),
    ]);

    $token = basename((string) $response->headers->get('Location'));
    $response->assertRedirect(route('admin.extensions.review', $token));

    $this->actingAs($this->admin)
        ->get(route('admin.extensions.review', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/extensions/review')
            ->where('staged.token', $token)
            ->where('staged.package.slug', 'tour-dates')
            ->where('staged.package.permissions', ['manage_terms', 'upload_files'])
            ->where('staged.isUpdate', false)
            ->where('capabilities.upload_files', 'Upload files')
        );

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse();
});

it('shows every problem of an unsafe upload', function () {
    $zip = PackageZip::plugin()->add('../../public/shell.php', '<?php')->symlink('tour-dates/passwords.txt', '/etc/passwd');

    $this->actingAs($this->admin)
        ->from(route('admin.plugins.index', ['tab' => 'add']))
        ->post(route('admin.extensions.upload'), ['kind' => 'plugin', 'package' => ($this->upload)($zip)])
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'add']))
        ->assertSessionHasErrors(['package' => "\"../../public/shell.php\" points outside the package folder.\n\"tour-dates/passwords.txt\" is a symbolic link."]);
});

it('validates the upload form', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.extensions.upload'), [
            'kind' => 'widget',
            'package' => UploadedFile::fake()->create('tour-dates.tar.gz', 10),
        ])
        ->assertSessionHasErrors(['kind', 'package']);
});

it('refuses uploads over the size limit', function () {
    config(['verso.extensions.max_upload_kb' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.upload'), ['kind' => 'plugin', 'package' => UploadedFile::fake()->create('tour-dates.zip', 20)])
        ->assertSessionHasErrors('package');
});

it('installs and enables an accepted plugin', function () {
    $staged = app(ExtensionInstaller::class)->stage(PackageZip::plugin()->build($this->sandbox->zipPath('tour-dates')), ExtensionKind::Plugin);

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $staged->token), ['activate' => true])
        ->assertRedirect(route('admin.plugins.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Tour Dates 1.0.0 installed and enabled.']);

    expect(app(PluginManager::class)->isEnabled('tour-dates'))->toBeTrue();
});

it('installs a theme and sends the admin to the themes page', function () {
    $staged = app(ExtensionInstaller::class)->stage(PackageZip::theme(manifest: ['parent' => 'default'])->build($this->sandbox->zipPath('lighthouse')), ExtensionKind::Theme);

    $this->actingAs($this->admin)
        ->post(route('admin.extensions.install', $staged->token))
        ->assertRedirect(route('admin.themes.index'));

    expect(app(ThemeManager::class)->find('lighthouse'))->not->toBeNull();
});

it('reports a missing or expired upload', function () {
    $token = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    $this->actingAs($this->admin)->get(route('admin.extensions.review', $token))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'add']))
        ->assertInertiaFlash('toast.type', 'error');

    $this->actingAs($this->admin)->post(route('admin.extensions.install', $token))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'add']))
        ->assertInertiaFlash('toast.type', 'error');
});

it('discards an upload', function () {
    $staged = app(ExtensionInstaller::class)->stage(PackageZip::plugin()->build($this->sandbox->zipPath('tour-dates')), ExtensionKind::Plugin);

    $this->actingAs($this->admin)
        ->delete(route('admin.extensions.discard', $staged->token))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'add']));

    expect(File::exists(dirname($staged->zipPath)))->toBeFalse();
});

it('removes a plugin and refuses to remove the active theme', function () {
    $installer = app(ExtensionInstaller::class);
    $installer->install($installer->stage(PackageZip::plugin()->build($this->sandbox->zipPath('tour-dates')), ExtensionKind::Plugin)->token, true);

    $this->actingAs($this->admin)
        ->from(route('admin.plugins.index'))
        ->delete(route('admin.extensions.remove', ['plugin', 'tour-dates']))
        ->assertRedirect(route('admin.plugins.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The plugin "tour-dates" was removed. Its data was kept.']);

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse();

    $this->actingAs($this->admin)
        ->delete(route('admin.extensions.remove', ['theme', 'default']))
        ->assertInertiaFlash('toast.type', 'error');

    $this->actingAs($this->admin)->delete(route('admin.extensions.remove', ['widget', 'default']))->assertNotFound();
});

it('goes back to the list after removing from a detail page', function () {
    $installer = app(ExtensionInstaller::class);
    $installer->install($installer->stage(PackageZip::plugin()->build($this->sandbox->zipPath('tour-dates')), ExtensionKind::Plugin)->token);
    $installer->install($installer->stage(PackageZip::theme()->build($this->sandbox->zipPath('lighthouse')), ExtensionKind::Theme)->token);

    $this->actingAs($this->admin)
        ->from(route('admin.plugins.show', 'tour-dates'))
        ->delete(route('admin.extensions.remove', ['plugin', 'tour-dates']), ['delete_data' => false])
        ->assertRedirect(route('admin.plugins.index'));

    $this->actingAs($this->admin)
        ->from(route('admin.themes.show', 'lighthouse'))
        ->delete(route('admin.extensions.remove', ['theme', 'lighthouse']))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'themes']));
});

it('stays on the detail page when the removal fails', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.themes.show', 'default'))
        ->delete(route('admin.extensions.remove', ['theme', 'default']))
        ->assertRedirect(route('admin.themes.show', 'default'))
        ->assertInertiaFlash('toast.type', 'error');
});

it('marks removable plugins and themes on their pages', function () {
    $installer = app(ExtensionInstaller::class);
    $installer->install($installer->stage(PackageZip::plugin()->build($this->sandbox->zipPath('tour-dates')), ExtensionKind::Plugin)->token);
    $installer->install($installer->stage(PackageZip::theme()->build($this->sandbox->zipPath('lighthouse')), ExtensionKind::Theme)->token);

    $this->actingAs($this->admin)->get(route('admin.plugins.index'))
        ->assertInertia(fn (Assert $page) => $page->where('plugins.0.slug', 'tour-dates')->where('plugins.0.removable', true));

    $this->actingAs($this->admin)->get(route('admin.themes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('themes.0.slug', 'lighthouse')
            ->where('themes.0.removable', true)
            ->where('themes.1.slug', 'default')
            ->where('themes.1.removable', false)
        );
});

it('lists registry packages with their installed version', function () {
    config(['verso.extensions.registry.url' => 'https://registry.verso.example/index.json']);
    Http::fake(['registry.verso.example/index.json' => Http::response(['extensions' => [
        ['name' => 'Tour Dates', 'slug' => 'tour-dates', 'version' => '1.4.0', 'download_url' => 'https://registry.verso.example/tour-dates.zip', 'sha256' => str_repeat('a', 64)],
        ['type' => 'theme', 'name' => 'Lighthouse', 'slug' => 'lighthouse', 'version' => '2.0.0', 'download_url' => 'https://registry.verso.example/lighthouse.zip', 'sha256' => str_repeat('b', 64)],
    ]])]);

    $installer = app(ExtensionInstaller::class);
    $installer->install($installer->stage(PackageZip::plugin('tour-dates', '1.2.0')->build($this->sandbox->zipPath('tour-dates')), ExtensionKind::Plugin)->token);

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index', ['tab' => 'browse']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'browse')
            ->where('registry.configured', true)
            ->where('registry.error', null)
            ->has('registry.entries', 2)
            ->where('registry.entries.0.installedVersion', '1.2.0')
            ->where('registry.entries.1.installedVersion', null)
        );
});

it('shows registry errors and the empty state', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index', ['tab' => 'browse']))
        ->assertInertia(fn (Assert $page) => $page->where('registry.configured', false));

    config(['verso.extensions.registry.url' => 'https://registry.verso.example/index.json']);
    Http::fake(['registry.verso.example/*' => Http::response('down', 500)]);

    $this->actingAs($this->admin)
        ->get(route('admin.plugins.index', ['tab' => 'browse']))
        ->assertInertia(fn (Assert $page) => $page->where('registry.error', fn (string $error) => str_contains($error, 'could not be reached')));
});

it('stages a registry package and refuses a bad checksum', function () {
    config(['verso.extensions.registry.url' => 'https://registry.verso.example/index.json']);
    $zip = File::get(PackageZip::plugin('tour-dates', '1.4.0')->build($this->sandbox->zipPath('tour-dates')));

    Http::fake([
        'registry.verso.example/index.json' => Http::response([
            ['name' => 'Tour Dates', 'slug' => 'tour-dates', 'version' => '1.4.0', 'download_url' => 'https://registry.verso.example/tour-dates.zip', 'sha256' => hash('sha256', $zip)],
            ['name' => 'Recipe Cards', 'slug' => 'recipe-cards', 'version' => '1.0.0', 'download_url' => 'https://registry.verso.example/recipe-cards.zip', 'sha256' => str_repeat('c', 64)],
        ]),
        'registry.verso.example/tour-dates.zip' => Http::response($zip),
        'registry.verso.example/recipe-cards.zip' => Http::response('tampered bytes'),
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.extensions.registry', ['plugin', 'tour-dates']));
    expect((string) $response->headers->get('Location'))->toContain('/admin/extensions/review/');

    $this->actingAs($this->admin)->post(route('admin.extensions.registry', ['plugin', 'recipe-cards']))
        ->assertRedirect(route('admin.plugins.index', ['tab' => 'add']))
        ->assertInertiaFlash('toast.message', 'The download of Recipe Cards does not match its sha256 sum, so it was not installed.');
});

it('merges the extensions page into Plugins and themes in the admin nav', function () {
    expect(AdminNav::has('extensions'))->toBeFalse()
        ->and(AdminNav::get('plugins')?->label)->toBe('Plugins and themes');
});
