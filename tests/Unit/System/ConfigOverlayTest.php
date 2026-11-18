<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\System\Config\ConfigGroup;
use App\Verso\System\Config\ConfigOverlay;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Config\ParameterType;
use App\Verso\System\Config\Unit;
use Illuminate\Config\Repository;

function newsroomConfig(): Repository
{
    return new Repository(['newsroom' => ['max_upload_kb' => 10240, 'per_page' => 20, 'image_format' => 'webp', 'cache' => true]]);
}

/** @return list<ConfigParameter> */
function newsroomParameters(): array
{
    return [
        new ConfigParameter('newsroom.max_upload_kb', 'uploads', 'Largest upload', unit: Unit::Kilobytes, min: 1, max: 102400, env: 'NEWSROOM_TEST_MAX_UPLOAD_KB'),
        new ConfigParameter('newsroom.per_page', 'uploads', 'Rows per page', unit: Unit::Items, min: 5, max: 200),
        new ConfigParameter('newsroom.image_format', 'uploads', 'Image format', type: ParameterType::Choice, options: ['webp' => 'WebP', 'jpg' => 'JPEG']),
        new ConfigParameter('newsroom.cache', 'uploads', 'Cache', type: ParameterType::Boolean),
    ];
}

function newsroomOverlay(Repository $config, ?ConfigRegistry $registry = null): ConfigOverlay
{
    $registry ??= new ConfigRegistry;
    $registry->addGroup(new ConfigGroup('uploads', 'Uploads and media'));
    $registry->add(...newsroomParameters());

    return new ConfigOverlay($config, $registry, app(Settings::class));
}

afterEach(function () {
    putenv('NEWSROOM_TEST_MAX_UPLOAD_KB');
    unset($_ENV['NEWSROOM_TEST_MAX_UPLOAD_KB'], $_SERVER['NEWSROOM_TEST_MAX_UPLOAD_KB']);
});

it('puts saved values on top of the config', function () {
    app(Settings::class)->setMany(['system.config.newsroom.max_upload_kb' => 51200, 'system.config.newsroom.image_format' => 'jpg', 'system.config.newsroom.cache' => false]);
    $config = newsroomConfig();

    newsroomOverlay($config)->boot();

    expect($config->get('newsroom.max_upload_kb'))->toBe(51200)
        ->and($config->get('newsroom.image_format'))->toBe('jpg')
        ->and($config->get('newsroom.cache'))->toBeFalse()
        ->and($config->get('newsroom.per_page'))->toBe(20);
});

it('falls back to the default and reports invalid saved values', function () {
    app(Settings::class)->setMany(['system.config.newsroom.per_page' => 5000, 'system.config.newsroom.image_format' => 'tiff']);
    $config = newsroomConfig();
    $overlay = newsroomOverlay($config);

    $overlay->boot();

    expect($config->get('newsroom.per_page'))->toBe(20)
        ->and($config->get('newsroom.image_format'))->toBe('webp')
        ->and($overlay->problems())->toBe([
            'newsroom.per_page' => 'Use 200 items or less.',
            'newsroom.image_format' => 'Choose one of the listed values.',
        ]);
});

it('keeps a value set by an env var and ignores the saved one', function () {
    putenv('NEWSROOM_TEST_MAX_UPLOAD_KB=2048');
    $_ENV['NEWSROOM_TEST_MAX_UPLOAD_KB'] = $_SERVER['NEWSROOM_TEST_MAX_UPLOAD_KB'] = '2048';
    app(Settings::class)->set('system.config.newsroom.max_upload_kb', 51200);
    $config = newsroomConfig();
    $config->set('newsroom.max_upload_kb', 2048);
    $overlay = newsroomOverlay($config);

    $overlay->boot();
    $parameter = $overlay->problems() === [] ? newsroomParameters()[0] : null;

    expect($config->get('newsroom.max_upload_kb'))->toBe(2048)
        ->and($overlay->isSetByEnvironment($parameter))->toBeTrue()
        ->and($overlay->isSetByEnvironment(newsroomParameters()[1]))->toBeFalse();
});

it('applies settings that plugins add after boot', function () {
    app(Settings::class)->set('system.config.bookshop.per_page', 48);
    $config = newsroomConfig();
    $config->set('bookshop.per_page', 12);
    $registry = new ConfigRegistry;
    newsroomOverlay($config, $registry)->boot();

    $registry->add(new ConfigParameter('bookshop.per_page', 'plugin-bookshop', 'Products per page', unit: Unit::Items, min: 1, max: 200));

    expect($config->get('bookshop.per_page'))->toBe(48);
});

it('works with a cached config: the first defaults are kept and a removed value goes back to them', function () {
    app(Settings::class)->set('system.config.newsroom.per_page', 50);
    $config = newsroomConfig();
    newsroomOverlay($config)->boot();

    // config:cache writes the booted config to a PHP file. Read it back like Laravel does.
    $cached = new Repository(eval('return '.var_export($config->all(), true).';'));
    app(Settings::class)->forget('system.config.newsroom.per_page');
    $overlay = newsroomOverlay($cached);
    $overlay->boot();

    expect($cached->get('newsroom.per_page'))->toBe(20)
        ->and($overlay->defaultValue(newsroomParameters()[1]))->toBe(20);
});

it('leaves the config alone when the settings table is missing', function () {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('all')->andThrow(new RuntimeException('no such table: settings'));
    $config = newsroomConfig();
    $registry = new ConfigRegistry;
    $registry->add(...newsroomParameters());

    (new ConfigOverlay($config, $registry, $settings))->boot();

    expect($config->get('newsroom.max_upload_kb'))->toBe(10240);
});
