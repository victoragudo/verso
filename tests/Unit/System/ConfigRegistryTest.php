<?php

use App\Verso\System\Config\ConfigGroup;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Config\CoreParameters;
use App\Verso\System\Config\ParameterType;
use App\Verso\System\Config\Unit;

it('refuses a setting declared twice', function () {
    $registry = new ConfigRegistry;
    $registry->add(new ConfigParameter('verso.media.per_page', 'uploads', 'Items per page'));

    $registry->add(new ConfigParameter('verso.media.per_page', 'uploads', 'Items per page again'));
})->throws(InvalidArgumentException::class);

it('lists groups in order and hides groups without settings', function () {
    $registry = new ConfigRegistry;
    $registry->addGroup(new ConfigGroup('api', 'API', order: 60));
    $registry->addGroup(new ConfigGroup('uploads', 'Uploads and media', order: 10));
    $registry->addGroup(new ConfigGroup('email', 'Email', order: 40));
    $registry->add(new ConfigParameter('verso.api.rate_limit', 'api', 'Requests per minute'), new ConfigParameter('verso.media.per_page', 'uploads', 'Items per page'));

    expect(array_map(fn (ConfigGroup $group): string => $group->key, $registry->groups()))->toBe(['uploads', 'api']);
});

it('checks types, ranges and formats', function (array $options, mixed $value, ?string $problem) {
    $parameter = new ConfigParameter('newsroom.value', 'uploads', 'Value', ...$options);

    expect($parameter->problem($value))->toBe($problem);
})->with([
    'whole number in range' => [['unit' => Unit::Kilobytes, 'min' => 1, 'max' => 1024], '512', null],
    'too big' => [['unit' => Unit::Kilobytes, 'min' => 1, 'max' => 1024], 2048, 'Use 1,024 KB or less.'],
    'too small' => [['unit' => Unit::Items, 'min' => 5], 2, 'Use 5 items or more.'],
    'not a number' => [[], 'twelve', 'Enter a whole number.'],
    'decimal for integer' => [[], 1.5, 'Enter a whole number.'],
    'boolean text' => [['type' => ParameterType::Boolean], 'true', null],
    'boolean junk' => [['type' => ParameterType::Boolean], 'maybe', 'Choose on or off.'],
    'email' => [['type' => ParameterType::Text, 'format' => ConfigParameter::FORMAT_EMAIL], 'news@harbourlight.org', null],
    'bad email' => [['type' => ParameterType::Text, 'format' => ConfigParameter::FORMAT_EMAIL], 'harbourlight', 'Enter an email address like news@example.com.'],
    'host' => [['type' => ParameterType::Text, 'format' => ConfigParameter::FORMAT_HOST], 'smtp.harbourlight.org', null],
    'bad host' => [['type' => ParameterType::Text, 'format' => ConfigParameter::FORMAT_HOST], 'smtp server; rm', 'Enter a host name like smtp.example.com or an IP address.'],
    'list from text' => [['type' => ParameterType::TextList], 'image/jpeg, image/png', null],
]);

it('declares every core setting with a config key that exists and a valid default', function () {
    $registry = new ConfigRegistry;
    (new CoreParameters)->register($registry);

    foreach ($registry->all() as $parameter) {
        expect(config()->has($parameter->key))->toBeTrue("Missing config key {$parameter->key}");

        if ($parameter->env === null || env($parameter->env) === null) {
            expect($parameter->problem(config($parameter->key)))->toBeNull("Bad default for {$parameter->key}");
        }
    }

    expect($registry->groups())->toHaveCount(6);
});

it('lets admins change the theme check limits in System settings', function () {
    $registry = new ConfigRegistry;
    (new CoreParameters)->register($registry);
    $keys = array_map(fn (ConfigParameter $parameter): string => $parameter->key, $registry->all());

    expect($keys)->toContain(
        'verso.extensions.verification.themes.screenshot_max_kb',
        'verso.extensions.verification.themes.screenshot_max_width',
        'verso.extensions.verification.themes.screenshot_max_height',
        'verso.extensions.verification.themes.preview_posts',
        'verso.extensions.verification.themes.preview_max_kb',
    );
});
