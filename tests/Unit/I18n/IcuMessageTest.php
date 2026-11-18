<?php

use App\Verso\I18n\Support\IcuMessage;
use App\Verso\I18n\Support\PluralRules;

it('fills simple arguments', function () {
    expect(IcuMessage::format('Saved “{title}”.', ['title' => 'Spring menu'], 'en'))->toBe('Saved “Spring menu”.');
});

it('keeps a missing argument visible', function () {
    expect(IcuMessage::format('Hello {name}', [], 'en'))->toBe('Hello {name}');
});

it('picks the plural form of the language', function (string $locale, int $count, string $expected) {
    $message = '{count, plural, =0 {none} one {one: #} two {two: #} few {few: #} many {many: #} other {other: #}}';

    expect(IcuMessage::format($message, ['count' => $count], $locale))->toBe($expected);
})->with([
    ['en', 0, 'none'],
    ['en', 1, 'one: 1'],
    ['en', 5, 'other: 5'],
    ['ru', 21, 'one: 21'],
    ['ru', 3, 'few: 3'],
    ['ru', 11, 'many: 11'],
    ['ar', 2, 'two: 2'],
    ['ar', 105, 'few: 105'],
    ['ar', 11, 'many: 11'],
    ['ja', 1, 'other: 1'],
    ['fr', 1, 'one: 1'],
]);

it('formats numbers with the separators of the language', function () {
    expect(IcuMessage::format('{count, plural, one {# entry} other {# entries}}', ['count' => 1200], 'de'))->toBe('1.200 entries');
})->skip(! class_exists(NumberFormatter::class), 'needs intl');

it('handles select and nested arguments', function () {
    $message = '{role, select, admin {{name} runs the site} other {{name} writes}}';

    expect(IcuMessage::format($message, ['role' => 'admin', 'name' => 'Lena'], 'en'))->toBe('Lena runs the site')
        ->and(IcuMessage::format($message, ['role' => 'author', 'name' => 'Omar'], 'en'))->toBe('Omar writes');
});

it('reads quoted braces and double apostrophes as text', function () {
    expect(IcuMessage::format("Use '{name}' for the name, it''s easy", ['name' => 'x'], 'en'))->toBe("Use {name} for the name, it's easy");
});

it('lists the arguments of a message', function () {
    expect(IcuMessage::arguments('{count, plural, one {# file in {folder}} other {# files}} by {user}'))->toBe(['count', 'folder', 'user']);
});

it('matches the plural rules of the intl extension', function (string $locale) {
    foreach ([0, 1, 2, 3, 5, 11, 12, 21, 22, 25, 100, 101, 111, 1000000] as $number) {
        $intl = MessageFormatter::formatMessage($locale, '{n, plural, zero {zero} one {one} two {two} few {few} many {many} other {other}}', ['n' => $number]);

        expect(PluralRules::select($locale, $number))->toBe($intl, "{$locale} {$number}");
    }
})->with(['en', 'es', 'zh', 'hi', 'ar', 'pt', 'bn', 'ru', 'ja', 'fr', 'de', 'id', 'tr', 'ko', 'it'])
    ->skip(! class_exists(MessageFormatter::class), 'needs intl');
