<?php

use App\Verso\Editor\Support\UrlSanitizer;

beforeEach(function () {
    $this->urls = new UrlSanitizer(['http', 'https', 'mailto', 'tel']);
});

it('keeps web, mail and phone links', function (string $url) {
    expect($this->urls->clean($url))->toBe($url);
})->with([
    'https://www.lonelyplanet.com/portugal/porto',
    'http://example.org/menu.pdf',
    'mailto:reservas@casadachica.pt',
    'tel:+351222000111',
    'HTTPS://EXAMPLE.ORG/UPPER',
]);

it('keeps relative links', function (string $url) {
    expect($this->urls->clean($url))->toBe($url);
})->with([
    '/blog/three-days-in-porto',
    '#day-two',
    '?page=2',
    'images/ribeira.jpg',
    '//cdn.example.org/ribeira.jpg',
]);

it('rejects dangerous schemes', function (string $url) {
    expect($this->urls->clean($url))->toBeNull()
        ->and($this->urls->isSafe($url))->toBeFalse();
})->with([
    'javascript:alert(document.cookie)',
    'JavaScript:alert(1)',
    ' javascript:alert(1)',
    "java\tscript:alert(1)",
    "java\nscript:alert(1)",
    "\x01javascript:alert(1)",
    'vbscript:msgbox(1)',
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'data:image/svg+xml,<svg onload=alert(1)>',
    'file:///etc/passwd',
]);

it('returns null for empty or non text values', function (mixed $value) {
    expect($this->urls->clean($value))->toBeNull();
})->with([null, '', '   ', 42, [['https://example.org']]]);

it('trims spaces and removes control characters', function () {
    expect($this->urls->clean("  https://example.org/porto\n "))->toBe('https://example.org/porto');
});

it('accepts only absolute web links in http mode', function () {
    expect($this->urls->cleanHttp('https://vimeo.com/76979871'))->toBe('https://vimeo.com/76979871')
        ->and($this->urls->cleanHttp('/blog/porto'))->toBeNull()
        ->and($this->urls->cleanHttp('mailto:ana@example.org'))->toBeNull()
        ->and($this->urls->cleanHttp('javascript:alert(1)'))->toBeNull();
});

it('uses the configured schemes', function () {
    $urls = new UrlSanitizer(['HTTPS']);

    expect($urls->clean('https://example.org'))->toBe('https://example.org')
        ->and($urls->clean('http://example.org'))->toBeNull();
});

it('is built from the verso config', function () {
    config(['verso.editor.allowed_url_schemes' => ['https']]);
    app()->forgetInstance(UrlSanitizer::class);

    expect(app(UrlSanitizer::class)->clean('mailto:ana@example.org'))->toBeNull();
});
