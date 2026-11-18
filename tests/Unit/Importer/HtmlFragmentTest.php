<?php

use App\Verso\Importer\Content\HtmlFragment;

it('finds the first element of any of the given tags', function () {
    $fragment = HtmlFragment::parse('<figure><h3>Ferry times</h3><h2>Ignored</h2></figure>');

    expect($fragment->first('h2', 'h3')?->tagName)->toBe('h3')
        ->and($fragment->first('table'))->toBeNull();
});

it('reads inner HTML and keeps accents', function () {
    $fragment = HtmlFragment::parse('<p>Pastéis de <strong>nata</strong> em Belém</p>');

    expect($fragment->innerHtml('p'))->toBe('Pastéis de <strong>nata</strong> em Belém')
        ->and($fragment->innerHtml('blockquote'))->toBe('')
        ->and($fragment->text())->toBe('Pastéis de nata em Belém');
});

it('lists every element of a tag', function () {
    $fragment = HtmlFragment::parse('<ul><li>Porto</li><li>Braga</li><li>Coimbra</li></ul>');

    expect(array_map(fn ($li) => $li->textContent, $fragment->all('li')))->toBe(['Porto', 'Braga', 'Coimbra']);
});

it('removes elements', function () {
    $fragment = HtmlFragment::parse('<blockquote><p>Slow down.</p><cite>Maria</cite></blockquote>');

    expect($fragment->remove('cite')->html())->toBe('<blockquote><p>Slow down.</p></blockquote>');
});

it('survives broken HTML', function () {
    $fragment = HtmlFragment::parse('<p>Unclosed <em>tag<figure><figcaption>Caption</p>');

    expect($fragment->innerHtml('figcaption'))->toContain('Caption');
});
