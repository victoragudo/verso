<?php

use App\Verso\Theme\Customizer\ThemeSettingField;
use App\Verso\Theme\Customizer\ThemeSettingType;
use App\Verso\Theme\Support\SearchHighlighter;
use App\Verso\Theme\Support\TableOfContents;

describe('table of contents', function () {
    it('gives h2 headings ids and lists them', function () {
        $toc = TableOfContents::from('<p>Intro</p><h2>Where we ate</h2><p>Text</p><h2 id="river">Sunday on the <em>river</em></h2><h3>Not listed</h3><h2>Where we ate</h2>');

        expect($toc->items)->toBe([
            ['id' => 'where-we-ate', 'text' => 'Where we ate'],
            ['id' => 'river', 'text' => 'Sunday on the river'],
            ['id' => 'where-we-ate-2', 'text' => 'Where we ate'],
        ])
            ->and($toc->html)->toContain('<h2 id="where-we-ate">Where we ate</h2>')
            ->and($toc->html)->toContain('<h2 id="river">Sunday on the <em>river</em></h2>')
            ->and($toc->html)->toContain('<h2 id="where-we-ate-2">')
            ->and($toc->isUseful(3))->toBeTrue()
            ->and($toc->isUseful(4))->toBeFalse();
    });

    it('leaves HTML without headings alone', function () {
        $toc = TableOfContents::from('<p>Only a short note about the market.</p>');

        expect($toc->items)->toBe([])
            ->and($toc->html)->toBe('<p>Only a short note about the market.</p>');
    });
});

describe('search highlighter', function () {
    it('marks every word of the search and escapes the text', function () {
        expect((string) (new SearchHighlighter(2))->mark('Bread & butter: bake the BREAD early', 'bread bake'))
            ->toBe('<mark>Bread</mark> &amp; butter: <mark>bake</mark> the <mark>BREAD</mark> early');
    });

    it('skips words shorter than the minimum', function () {
        expect((string) (new SearchHighlighter(3))->mark('A walk to Porto', 'a to porto'))
            ->toBe('A walk to <mark>Porto</mark>');
    });

    it('never lets a search inject HTML or break an entity', function () {
        expect((string) (new SearchHighlighter(2))->mark('Tips <b>for</b> tomatoes', '<b>'))
            ->toBe('Tips <mark>&lt;b&gt;</mark>for&lt;/b&gt; tomatoes')
            ->and((string) (new SearchHighlighter(2))->mark('Salt & pepper', 'amp'))
            ->toBe('Salt &amp; pepper');
    });
});

describe('toggle setting', function () {
    it('accepts booleans and casts form values', function () {
        $field = ThemeSettingField::fromArray('show_author_box', ['type' => 'toggle', 'default' => true], [], 500);

        expect($field->type)->toBe(ThemeSettingType::Toggle)
            ->and($field->accepts(false))->toBeTrue()
            ->and($field->accepts('yes'))->toBeFalse()
            ->and($field->cast('0'))->toBeFalse()
            ->and($field->cast('1'))->toBeTrue()
            ->and($field->cast(''))->toBeNull()
            ->and($field->cssValue(true, []))->toBeNull();
    });

    it('cannot set a CSS variable', function () {
        ThemeSettingField::fromArray('show_author_box', ['type' => 'toggle', 'default' => true, 'css' => '--author'], [], 500);
    })->throws(InvalidArgumentException::class, 'cannot be a CSS variable');
});
