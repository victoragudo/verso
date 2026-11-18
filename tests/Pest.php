<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Core', 'Unit/Content', 'Unit/Editor', 'Unit/Media', 'Unit/Site', 'Unit/Users', 'Unit/Comments', 'Unit/Plugins', 'Unit/Api', 'Unit/Install', 'Unit/Importer', 'Unit/Theme', 'Unit/Fields', 'Unit/Extensions', 'Unit/Updates', 'Unit/System', 'Unit/Seo', 'Unit/Easy', 'Unit/I18n', 'Unit/Shortcodes');

// Install pipeline tests point the default connection at a temporary SQLite file, so they run without RefreshDatabase.
pest()->extend(TestCase::class)->in('Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

// Strict compare of arrays read from JSON columns. MySQL sorts object keys, so key order is ignored (list order is not).
expect()->extend('toBeJsonData', function (array $expected) {
    return expect(sortJsonKeys($this->value))->toBe(sortJsonKeys($expected));
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// Sort the keys of every object in a decoded JSON value, keeping lists in their order.
function sortJsonKeys(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(sortJsonKeys(...), $value);

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}
