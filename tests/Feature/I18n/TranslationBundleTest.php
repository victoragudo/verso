<?php

use App\Verso\I18n\Translate;
use App\Verso\I18n\Translation\TranslationBundle;
use App\Verso\I18n\Translation\TranslationNamespaces;
use Illuminate\Support\Facades\File;

// A plugin lang folder in a temporary place, added like an enabled plugin.
function harborLang(array $files): string
{
    $folder = sys_get_temp_dir().'/verso-harbor-lang-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($folder);

    foreach ($files as $locale => $lines) {
        File::put($folder.'/'.$locale.'.json', json_encode($lines, JSON_UNESCAPED_UNICODE));
    }

    app(TranslationNamespaces::class)->add('harbor-weather', $folder);
    app()->forgetInstance('translator');

    return $folder;
}

afterEach(function () {
    app(TranslationNamespaces::class)->forget('harbor-weather');
});

it('serves the strings of a language with a long cache', function () {
    harborLang(['es' => ['Tide table' => 'Tabla de mareas']]);
    $bundle = app(TranslationBundle::class);
    $version = $bundle->version('es');

    $response = $this->get(route('verso.i18n.bundle', ['locale' => 'es', 'version' => $version]))
        ->assertOk()
        ->assertJsonPath('harbor-weather::Tide table', 'Tabla de mareas');

    expect($response->headers->get('Cache-Control'))->toContain('immutable')->toContain('public')
        ->and($response->headers->get('ETag'))->toContain($version);
});

it('does not cache an old version', function () {
    $response = $this->get(route('verso.i18n.bundle', ['locale' => 'de', 'version' => 'abc123']))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

it('answers 304 when the browser has the bundle', function () {
    $version = app(TranslationBundle::class)->version('fr');

    $this->withHeader('If-None-Match', '"'.$version.'"')
        ->get(route('verso.i18n.bundle', ['locale' => 'fr', 'version' => $version]))
        ->assertStatus(304);
});

it('returns 404 for languages that are not enabled', function () {
    $this->get('/i18n/nl/abc.json')->assertNotFound();
});

it('changes the version when a file changes', function () {
    $folder = harborLang(['ja' => ['Tide table' => '潮汐表']]);
    $before = app(TranslationBundle::class)->version('ja');

    app()->forgetInstance(TranslationBundle::class);
    File::put($folder.'/ja.json', json_encode(['Tide table' => '潮見表', 'Wind' => '風'], JSON_UNESCAPED_UNICODE));
    clearstatcache();

    expect(app(TranslationBundle::class)->version('ja'))->not->toBe($before);
});

it('leaves out empty translations so English shows', function () {
    harborLang(['it' => ['Tide table' => '', 'Wind' => 'Vento']]);

    $messages = app(TranslationBundle::class)->messages('it');

    expect($messages)->toHaveKey('harbor-weather::Wind')->not->toHaveKey('harbor-weather::Tide table');
});

it('lets PHP read plugin strings with the slug prefix', function () {
    harborLang(['en' => ['Tide table' => 'Tide table'], 'de' => ['Tide table' => 'Gezeitentabelle']]);
    app()->setLocale('de');

    expect(__('harbor-weather::Tide table'))->toBe('Gezeitentabelle')
        ->and(Translate::text('Tide table', plugin: 'harbor-weather'))->toBe('Gezeitentabelle');

    app()->setLocale('ko');

    expect(__('harbor-weather::Tide table'))->toBe('Tide table')
        ->and(Translate::text('harbor-weather::Swell'))->toBe('Swell');
});

it('formats plurals in PHP', function () {
    harborLang(['ru' => ['{count, plural, one {# station} other {# stations}}' => '{count, plural, one {# станция} few {# станции} many {# станций} other {# станции}}']]);
    app()->setLocale('ru');

    expect(Translate::icu('harbor-weather::{count, plural, one {# station} other {# stations}}', ['count' => 3]))->toBe('3 станции')
        ->and(Translate::icu('harbor-weather::{count, plural, one {# station} other {# stations}}', ['count' => 5]))->toBe('5 станций');
});

it('sends no bundle URL for English', function () {
    expect(app(TranslationBundle::class)->needsBundle('en'))->toBeFalse()
        ->and(app(TranslationBundle::class)->needsBundle('es'))->toBeTrue();
});

it('works before install', function () {
    config(['verso.install.redirect' => true]);
    $lock = config('verso.install.lock_file');

    $this->get(route('verso.i18n.bundle', ['locale' => 'es', 'version' => 'abc']))->assertOk();

    expect($lock)->toBeString();
});

it('uses the core translation when a plugin has none', function () {
    harborLang(['en' => ['Tide table' => 'Tide table'], 'es' => ['Tide table' => 'Tabla de mareas']]);
    $core = sys_get_temp_dir().'/verso-core-lang-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($core);
    File::put($core.'/es.json', json_encode(['Settings' => 'Ajustes']));
    app('translator')->addJsonPath($core);
    app()->setLocale('es');

    expect(Translate::text('Settings', plugin: 'harbor-weather'))->toBe('Ajustes')
        ->and(Translate::text('Tide table', plugin: 'harbor-weather'))->toBe('Tabla de mareas')
        ->and(Translate::text('Wind speed', plugin: 'harbor-weather'))->toBe('Wind speed');
});
