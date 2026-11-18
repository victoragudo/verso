<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Extensions\Actions\DeleteExtensionData;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\ExtensionInstaller;
use App\Verso\Site\Models\SitePart;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ExtensionSandbox;
use Tests\Support\PackageZip;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    $this->installer = app(ExtensionInstaller::class);
    $this->admin = User::factory()->admin()->create(['name' => 'Lucia Moreno']);
    $this->settings = app(Settings::class);
});

afterEach(function () {
    $this->sandbox->destroy();
});

// Installs and enables "tour-dates" with a table, then saves a few of its settings.
function installTourDates(object $test): void
{
    $zip = PackageZip::plugin('tour-dates', '1.0.0')->add('tour-dates/database/migrations/2026_12_01_000000_create_tour_dates_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('tour_dates', function (Blueprint $table) {
                    $table->id();
                    $table->string('city');
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('tour_dates');
            }
        };
        PHP);

    $installer = app(ExtensionInstaller::class);
    $installer->install($installer->stage($zip->build($test->sandbox->zipPath(uniqid())), ExtensionKind::Plugin)->token, true);

    app(Settings::class)->setMany([
        'tour-dates.ticket_shop' => 'https://tickets.example.es',
        'tour-dates.show_past' => false,
        'tour-dates-archive.enabled' => true,
        'site.name' => 'Lucia Moreno Live',
    ]);
}

function installLighthouse(object $test): void
{
    $installer = app(ExtensionInstaller::class);
    $installer->install($installer->stage(PackageZip::theme('lighthouse')->build($test->sandbox->zipPath(uniqid())), ExtensionKind::Theme)->token);

    app(Settings::class)->setMany([
        'theme.lighthouse.accent' => '#0f766e',
        'theme.lighthouse.footer_text' => 'Stories from the coast',
        'theme.lighthouse-dark.accent' => '#111827',
        'theme.default.accent' => '#9a3412',
    ]);
}

it('keeps the plugin tables and settings by default', function () {
    installTourDates($this);

    $this->installer->remove(ExtensionKind::Plugin, 'tour-dates');

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse()
        ->and(Schema::hasTable('tour_dates'))->toBeTrue()
        ->and($this->settings->get('tour-dates.ticket_shop'))->toBe('https://tickets.example.es');
});

it('runs the plugin migrations down and deletes its settings when asked', function () {
    installTourDates($this);

    $this->installer->remove(ExtensionKind::Plugin, 'tour-dates', deleteData: true);

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeFalse()
        ->and(Schema::hasTable('tour_dates'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', '2026_12_01_000000_create_tour_dates_table')->exists())->toBeFalse()
        ->and($this->settings->has('tour-dates.ticket_shop'))->toBeFalse()
        ->and($this->settings->has('tour-dates.show_past'))->toBeFalse()
        ->and($this->settings->get('tour-dates-archive.enabled'))->toBeTrue()
        ->and($this->settings->get('site.name'))->toBe('Lucia Moreno Live');
});

it('keeps the plugin when its data cannot be deleted', function () {
    $zip = PackageZip::plugin('tour-dates', '1.0.0')->add('tour-dates/database/migrations/2026_12_01_000000_create_tour_dates_table.php', <<<'PHP'
        <?php

        return new class extends Illuminate\Database\Migrations\Migration
        {
            public function up(): void {}

            public function down(): void
            {
                throw new RuntimeException('The archive table is locked.');
            }
        };
        PHP);
    $this->installer->install($this->installer->stage($zip->build($this->sandbox->zipPath(uniqid())), ExtensionKind::Plugin)->token, true);

    expect(fn () => $this->installer->remove(ExtensionKind::Plugin, 'tour-dates', deleteData: true))
        ->toThrow(ExtensionInstallFailed::class, 'The archive table is locked.');

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeTrue();
});

it('deletes only the settings of the removed theme when asked', function () {
    installLighthouse($this);

    $this->installer->remove(ExtensionKind::Theme, 'lighthouse', deleteData: true);

    expect(File::exists($this->sandbox->themesPath().'/lighthouse'))->toBeFalse()
        ->and($this->settings->has('theme.lighthouse.accent'))->toBeFalse()
        ->and($this->settings->has('theme.lighthouse.footer_text'))->toBeFalse()
        ->and($this->settings->get('theme.lighthouse-dark.accent'))->toBe('#111827')
        ->and($this->settings->get('theme.default.accent'))->toBe('#9a3412');
});

it('deletes the site editor parts of the removed theme when asked', function () {
    installLighthouse($this);
    $header = SitePart::query()->create(['theme' => 'lighthouse', 'part' => 'header', 'blocks' => [], 'status' => 'published']);
    $header->revisions()->create(['blocks' => [], 'user_id' => $this->admin->id]);
    SitePart::query()->create(['theme' => 'default', 'part' => 'footer', 'blocks' => [], 'status' => 'draft']);

    $this->installer->remove(ExtensionKind::Theme, 'lighthouse', deleteData: true);

    expect(SitePart::query()->where('theme', 'lighthouse')->exists())->toBeFalse()
        ->and(DB::table('site_part_revisions')->where('site_part_id', $header->id)->exists())->toBeFalse()
        ->and(SitePart::query()->where('theme', 'default')->exists())->toBeTrue();
});

it('keeps the site editor parts of a removed theme by default', function () {
    installLighthouse($this);
    SitePart::query()->create(['theme' => 'lighthouse', 'part' => 'header', 'blocks' => [], 'status' => 'draft']);

    $this->installer->remove(ExtensionKind::Theme, 'lighthouse');

    expect(SitePart::query()->where('theme', 'lighthouse')->exists())->toBeTrue();
});

it('keeps theme settings by default', function () {
    installLighthouse($this);

    $this->installer->remove(ExtensionKind::Theme, 'lighthouse');

    expect($this->settings->get('theme.lighthouse.accent'))->toBe('#0f766e');
});

it('never deletes data when the removal is refused', function () {
    installLighthouse($this);
    app(ThemeManager::class)->activate('lighthouse');

    expect(fn () => $this->installer->remove(ExtensionKind::Theme, 'lighthouse', deleteData: true))->toThrow(ExtensionInstallFailed::class);

    expect($this->settings->get('theme.lighthouse.accent'))->toBe('#0f766e');
});

it('removes a plugin with its data from the admin', function () {
    installTourDates($this);

    $this->actingAs($this->admin)
        ->from(route('admin.plugins.index'))
        ->delete(route('admin.extensions.remove', ['plugin', 'tour-dates']), ['delete_data' => true])
        ->assertRedirect(route('admin.plugins.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The plugin "tour-dates" was removed with its data.']);

    expect(Schema::hasTable('tour_dates'))->toBeFalse()
        ->and($this->settings->has('tour-dates.ticket_shop'))->toBeFalse();
});

it('keeps the data when the admin chooses to', function () {
    installTourDates($this);

    $this->actingAs($this->admin)
        ->delete(route('admin.extensions.remove', ['plugin', 'tour-dates']), ['delete_data' => false])
        ->assertInertiaFlash('toast.message', 'The plugin "tour-dates" was removed. Its data was kept.');

    expect(Schema::hasTable('tour_dates'))->toBeTrue();
});

it('removes a theme with its settings from the admin', function () {
    installLighthouse($this);

    $this->actingAs($this->admin)
        ->delete(route('admin.extensions.remove', ['theme', 'lighthouse']), ['delete_data' => true])
        ->assertInertiaFlash('toast.message', 'The theme "lighthouse" was removed with its data.');

    expect($this->settings->has('theme.lighthouse.accent'))->toBeFalse();
});

it('checks the delete_data flag', function () {
    installTourDates($this);

    $this->actingAs($this->admin)
        ->delete(route('admin.extensions.remove', ['plugin', 'tour-dates']), ['delete_data' => 'everything'])
        ->assertSessionHasErrors('delete_data');

    expect(File::exists($this->sandbox->pluginsPath().'/tour-dates'))->toBeTrue();
});

it('builds the setting prefixes from the slug', function () {
    expect(DeleteExtensionData::pluginSettingsPrefix('shop'))->toBe('shop.')
        ->and(DeleteExtensionData::themeSettingsPrefix('lighthouse'))->toBe('theme.lighthouse.');
});

it('deletes settings by prefix and reports the keys', function () {
    $this->settings->setMany(['newsletter.batch_size' => 50, 'newsletter.from_name' => 'Douro Journal', 'site.tagline' => 'Wine and walks']);

    expect($this->settings->forgetPrefix('newsletter.'))->toEqualCanonicalizing(['newsletter.batch_size', 'newsletter.from_name'])
        ->and($this->settings->forgetPrefix(''))->toBe([])
        ->and($this->settings->get('site.tagline'))->toBe('Wine and walks')
        ->and($this->settings->has('newsletter.batch_size'))->toBeFalse();
});
