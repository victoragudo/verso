<?php

use App\Verso\I18n\Locales;

function adminLocales(array $codes = ['en', 'es', 'zh-Hans', 'ar', 'pt-BR', 'de', 'ja']): Locales
{
    return new Locales($codes);
}

it('lists the enabled languages with English first', function () {
    expect(adminLocales(['de', 'es'])->codes())->toBe(['en', 'de', 'es']);
});

it('skips codes it has no names for', function () {
    expect(adminLocales(['es', 'xx'])->codes())->toBe(['en', 'es']);
});

it('normalizes regional and old codes to an admin language', function (?string $code, ?string $expected) {
    expect(adminLocales()->normalize($code))->toBe($expected);
})->with([
    ['es', 'es'],
    ['es_MX', 'es'],
    ['de-AT', 'de'],
    ['zh', 'zh-Hans'],
    ['zh_CN', 'zh-Hans'],
    ['ZH-hans', 'zh-Hans'],
    ['pt', 'pt-BR'],
    ['pt_PT', 'pt-BR'],
    ['nl', null],
    ['', null],
    [null, null],
]);

it('knows that Arabic is written right to left', function () {
    $locales = adminLocales();

    expect($locales->dir('ar'))->toBe('rtl')
        ->and($locales->get('ar')->isRtl())->toBeTrue()
        ->and($locales->dir('de'))->toBe('ltr');
});

it('falls back to English for unknown codes', function () {
    expect(adminLocales()->get('nl')->code)->toBe('en');
});

it('picks the best language from an Accept-Language header', function (string $header, ?string $expected) {
    expect(adminLocales()->fromAcceptLanguage($header))->toBe($expected);
})->with([
    ['de-CH,de;q=0.9,en;q=0.8', 'de'],
    ['nl-NL,nl;q=0.9,ja;q=0.7', 'ja'],
    ['fr;q=0.2,es;q=0.9', 'es'],
    ['pt-PT', 'pt-BR'],
    ['ar-EG,ar;q=0.9', 'ar'],
    ['nl,fy', null],
    ['*', null],
    ['es;q=0', null],
]);

it('shares names in their own language', function () {
    $list = adminLocales(['ja'])->toArray();

    expect($list[1])->toBe(['code' => 'ja', 'name' => '日本語', 'englishName' => 'Japanese', 'dir' => 'ltr']);
});
