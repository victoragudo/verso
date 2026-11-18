<?php

use App\Verso\Extensions\Verification\Scanners\PhpTokens;

it('drops whitespace and comments and keeps lines', function () {
    $tokens = new PhpTokens("<?php\n// A comment\n\$city = 'Porto';\n");

    expect($tokens->count())->toBe(4)
        ->and($tokens->text(0))->toBe('$city')
        ->and($tokens->line(0))->toBe(3)
        ->and($tokens->is(1, '='))->toBeTrue()
        ->and($tokens->line(3))->toBe(3);
});

it('reads the namespace and imports with aliases and groups', function () {
    $tokens = new PhpTokens("<?php\nnamespace Verso\\Plugins\\TourDates;\n\nuse App\\Models\\User;\nuse Illuminate\\Support\\Facades\\Http as Client;\nuse App\\Verso\\Content\\Actions\\{PublishEntry, TrashEntry as Trash};\nuse function strlen;\n\nclass Concerts { use HasDates; }\n");

    expect($tokens->namespace)->toBe('Verso\\Plugins\\TourDates')
        ->and($tokens->imports)->toBe([
            'user' => 'App\\Models\\User',
            'client' => 'Illuminate\\Support\\Facades\\Http',
            'publishentry' => 'App\\Verso\\Content\\Actions\\PublishEntry',
            'trash' => 'App\\Verso\\Content\\Actions\\TrashEntry',
        ]);
});

it('resolves class names like PHP does', function () {
    $tokens = new PhpTokens("<?php\nnamespace Verso\\Plugins\\TourDates;\nuse App\\Verso\\Content;\n");

    expect($tokens->resolve('\\DB'))->toBe('DB')
        ->and($tokens->resolve('Content\\Models\\Entry'))->toBe('App\\Verso\\Content\\Models\\Entry')
        ->and($tokens->resolve('Support\\Dates'))->toBe('Verso\\Plugins\\TourDates\\Support\\Dates')
        ->and($tokens->resolve('namespace\\Venue'))->toBe('Verso\\Plugins\\TourDates\\Venue');
});

it('splits call arguments and reads string literals', function () {
    $tokens = new PhpTokens("<?php\nfile_put_contents('dates.json', json_encode([1, 2]), \"a\\tb\");\n");
    $arguments = $tokens->arguments(1);

    expect($arguments)->toHaveCount(3)
        ->and($tokens->argumentLiteral($arguments[0]))->toBe('dates.json')
        ->and($tokens->argumentLiteral($arguments[1]))->toBeNull()
        ->and($tokens->argumentLiteral($arguments[2]))->toBe("a\tb")
        ->and($tokens->closing(1))->toBe($tokens->count() - 2);
});

it('remembers parse errors', function () {
    $tokens = new PhpTokens("<?php\n\nif (\n");

    expect($tokens->parseError)->not->toBeNull()
        ->and($tokens->parseErrorLine)->toBe(4);
});
