<?php

use App\Verso\Plugins\View\HeadTag;

it('prints meta and link tags with escaped attributes', function () {
    expect(HeadTag::meta(['name' => 'description', 'content' => 'Cafés & "bakeries" in Porto'])->toHtml())
        ->toBe('<meta name="description" content="Cafés &amp; &quot;bakeries&quot; in Porto">')
        ->and(HeadTag::link(['rel' => 'icon', 'href' => '/favicon.svg', 'sizes' => null])->toHtml())
        ->toBe('<link rel="icon" href="/favicon.svg">');
});

it('prints bare attributes and skips bad attribute names', function () {
    expect(HeadTag::script('https://cdn.example.org/map.js', ['defer' => true, 'async' => false, 'on"load' => 'x'])->toHtml())
        ->toBe('<script src="https://cdn.example.org/map.js" defer></script>');
});

it('keeps inline code from closing its tag early', function () {
    expect(HeadTag::script(code: 'const note = "</script><b>bold</b>";')->toHtml())
        ->toBe('<script>const note = "<\/script><b>bold</b>";</script>')
        ->and(HeadTag::style('body { color: #333 }</STYLE>')->toHtml())
        ->toBe('<style>body { color: #333 }<\/STYLE></style>');
});

it('refuses elements outside the allow list', function () {
    expect(fn () => new HeadTag('iframe', ['src' => 'https://example.org']))->toThrow(InvalidArgumentException::class);
});
