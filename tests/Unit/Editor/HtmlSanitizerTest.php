<?php

use App\Verso\Editor\Support\HtmlSanitizer;

beforeEach(function () {
    $this->sanitizer = app(HtmlSanitizer::class);
});

it('keeps allowed inline marks', function () {
    $html = 'We ate <strong>francesinha</strong>, <em>bacalhau</em> and <code>pastel de nata</code> on <u>Rua</u> <s>Augusta</s> <mark>twice</mark> H<sub>2</sub>O x<sup>2</sup>';

    expect($this->sanitizer->inline($html))->toBe($html);
});

it('turns b and i into the same tags', function () {
    expect($this->sanitizer->inline('<b>Bold</b> and <i>italic</i>'))->toBe('<b>Bold</b> and <i>italic</i>');
});

it('unwraps tags that are not allowed and keeps their text', function () {
    expect($this->sanitizer->inline('<p>The <span class="x">Douro</span> <div>river</div></p>'))->toBe('The Douro river');
});

it('returns an empty string for empty input', function () {
    expect($this->sanitizer->inline(''))->toBe('')
        ->and($this->sanitizer->inline("  \n "))->toBe('');
});

it('removes dangerous elements with all their content', function (string $html) {
    $clean = $this->sanitizer->inline('Before '.$html.' after');

    expect($clean)->toBe('Before  after');
})->with([
    'script' => '<script>alert(document.cookie)</script>',
    'script with src' => '<script src="https://evil.example/x.js"></script>',
    'uppercase script' => '<SCRIPT>alert(1)</SCRIPT>',
    'style' => '<style>body{display:none}</style>',
    'iframe' => '<iframe src="javascript:alert(1)"></iframe>',
    'object' => '<object data="evil.swf"></object>',
    'embed' => '<embed src="evil.swf">',
    'svg onload' => '<svg onload="alert(1)"><circle r="4"></circle></svg>',
    'math' => '<math><mi xlink:href="javascript:alert(1)">x</mi></math>',
    'img onerror' => '<img src=x onerror="alert(1)">',
    'form' => '<form action="https://evil.example"><input name="password"></form>',
    'template' => '<template><script>alert(1)</script></template>',
    'noscript' => '<noscript><img src=x onerror=alert(1)></noscript>',
    'textarea' => '<textarea><script>alert(1)</script></textarea>',
    'meta refresh' => '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">',
    'base' => '<base href="https://evil.example/">',
    'video' => '<video><source onerror="alert(1)"></video>',
]);

it('removes event handlers and other attributes', function (string $html, string $expected) {
    expect($this->sanitizer->inline($html))->toBe($expected);
})->with([
    ['<strong onclick="alert(1)">Porto</strong>', '<strong>Porto</strong>'],
    ['<em onmouseover=alert(1) style="color:red" class="lead" id="x">Lisbon</em>', '<em>Lisbon</em>'],
    ['<a href="/blog" onfocus="alert(1)" autofocus>blog</a>', '<a href="/blog">blog</a>'],
    ['<code data-x="1" ONCLICK="alert(1)">npm run dev</code>', '<code>npm run dev</code>'],
    ['<br onload="alert(1)">', '<br>'],
]);

it('keeps safe links and removes unsafe ones', function (string $html, string $expected) {
    expect($this->sanitizer->inline($html))->toBe($expected);
})->with([
    ['<a href="https://visitporto.travel" target="_blank" rel="opener">Visit Porto</a>', '<a href="https://visitporto.travel">Visit Porto</a>'],
    ['<a href="/blog/porto">post</a>', '<a href="/blog/porto">post</a>'],
    ['<a href="mailto:hello@verso.dev">write</a>', '<a href="mailto:hello@verso.dev">write</a>'],
    ['<a href="javascript:alert(1)">click</a>', '<a>click</a>'],
    ['<a href="JaVaScRiPt:alert(1)">click</a>', '<a>click</a>'],
    ['<a href="java&#x09;script:alert(1)">click</a>', '<a>click</a>'],
    ['<a href="&#106;avascript:alert(1)">click</a>', '<a>click</a>'],
    ['<a href="javascript&colon;alert(1)">click</a>', '<a>click</a>'],
    ['<a href=" &#14;javascript:alert(1)">click</a>', '<a>click</a>'],
    ['<a href="data:text/html,<script>alert(1)</script>">click</a>', '<a>click</a>'],
    ['<a href="vbscript:msgbox(1)">click</a>', '<a>click</a>'],
    ['<a>no link</a>', '<a>no link</a>'],
]);

it('escapes quotes and ampersands inside links', function () {
    expect($this->sanitizer->inline('<a href="/search?q=port&amp;wine=&quot;tawny&quot;">search</a>'))
        ->toBe('<a href="/search?q=port&amp;wine=&quot;tawny&quot;">search</a>');
});

it('escapes text that looks like markup', function () {
    expect($this->sanitizer->inline('5 &lt; 7 &amp;&amp; 9 &gt; 3'))->toBe('5 &lt; 7 &amp;&amp; 9 &gt; 3')
        ->and($this->sanitizer->inline('&lt;script&gt;alert(1)&lt;/script&gt;'))->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('cannot be tricked by broken or nested markup', function (string $html) {
    $clean = $this->sanitizer->inline($html);

    expect($clean)->not->toContain('<script')
        ->not->toContain('<img')
        ->not->toContain('<svg')
        ->not->toContain('<iframe')
        ->not->toMatch('/\son\w+\s*=/i')
        ->not->toMatch('/href="\s*javascript/i');
})->with([
    '<scr<script>ipt>alert(1)</scr</script>ipt>',
    '<<script>script>alert(1)<</script>/script>',
    '<a href="javascript:alert(1)"<b>x</b>',
    '<img """><script>alert(1)</script>">',
    '<strong><img src=x onerror=alert(1)//</strong>',
    '<!--<img src="--><img src=x onerror=alert(1)//">',
    '<a href=javascript&colon;alert(1)>x</a>',
    '<svg><p><style><img src=x onerror=alert(1)></style></p></svg>',
    '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
    '<form><math><mtext></form><form><mglyph><style></math><img src onerror=alert(1)>',
    '"><img src=x onerror=alert(1)>',
    '</strong><script>alert(1)</script>',
    '<a href="/ok" onclick=alert(1)//>x</a>',
    "<em\nonmouseover=alert(1)>hover</em>",
    '<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>',
]);

it('removes comments', function () {
    expect($this->sanitizer->inline('Porto<!-- secret note --> by night'))->toBe('Porto by night');
});

it('keeps unicode text and non breaking spaces', function () {
    expect($this->sanitizer->inline('Café São Bento&nbsp;— 東京'))->toBe("Café São Bento\u{00A0}— 東京");
});

it('allows extra tags when asked', function () {
    expect($this->sanitizer->clean('<p>One</p><p onclick="x">Two</p>', ['p']))->toBe('<p>One</p><p>Two</p>');
});

it('unwraps root tags when asked but keeps nested ones', function () {
    $html = '<ul><li>Porto<ul><li>Ribeira</li></ul></li><li>Lisbon</li></ul>';

    expect($this->sanitizer->clean($html, ['ul', 'ol', 'li'], ['ul', 'ol']))
        ->toBe('<li>Porto<ul><li>Ribeira</li></ul></li><li>Lisbon</li>');
});

it('returns plain text', function () {
    expect($this->sanitizer->text('<p>Day <strong>one</strong><br>in Porto</p><script>alert(1)</script>'))->toBe("Day one\nin Porto")
        ->and($this->sanitizer->text(''))->toBe('');
});
