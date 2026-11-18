<?php

use App\Verso\Editor\BlockRenderer;
use Dom\HTMLDocument;

// Every core block that takes user text, filled with the same attack.
function blocksWithPayload(string $payload): array
{
    return [
        ['id' => 'b_1', 'type' => 'paragraph', 'content' => $payload],
        ['id' => 'b_2', 'type' => 'heading', 'attrs' => ['level' => 2, 'anchor' => $payload], 'content' => $payload],
        ['id' => 'b_3', 'type' => 'list', 'attrs' => ['start' => $payload], 'content' => '<li>'.$payload.'</li>'],
        ['id' => 'b_4', 'type' => 'quote', 'attrs' => ['citation' => $payload], 'content' => $payload],
        ['id' => 'b_5', 'type' => 'code', 'attrs' => ['language' => $payload], 'content' => $payload],
        ['id' => 'b_6', 'type' => 'image', 'attrs' => ['src' => '/a.jpg', 'alt' => $payload, 'caption' => $payload, 'width' => $payload]],
        ['id' => 'b_7', 'type' => 'gallery', 'attrs' => ['columns' => $payload, 'caption' => $payload, 'images' => [['src' => '/a.jpg', 'alt' => $payload, 'caption' => $payload]]]],
        ['id' => 'b_8', 'type' => 'embed', 'attrs' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => $payload, 'caption' => $payload]],
        ['id' => 'b_9', 'type' => 'columns', 'children' => [['id' => 'b_9a', 'type' => 'column', 'attrs' => ['width' => $payload], 'children' => [['id' => 'b_9b', 'type' => 'paragraph', 'content' => $payload]]]]],
        ['id' => 'b_10', 'type' => 'button', 'attrs' => ['url' => '/', 'label' => $payload, 'style' => $payload]],
        ['id' => 'b_11', 'type' => 'table', 'attrs' => ['rows' => [[$payload, $payload]], 'caption' => $payload]],
        ['id' => 'b_12', 'type' => 'video', 'attrs' => ['src' => '/v.mp4', 'caption' => $payload]],
    ];
}

// Parses HTML like a browser and lists every problem an attacker could use.
function activeContent(string $html): array
{
    $document = HTMLDocument::createFromString('<!DOCTYPE html><body>'.$html, LIBXML_NOERROR);
    $problems = [];

    foreach ($document->body->querySelectorAll('*') as $element) {
        $tag = strtolower($element->localName);

        if (in_array($tag, ['script', 'style', 'svg', 'math', 'object', 'embed', 'form', 'base', 'meta', 'link', 'noscript'], true)) {
            $problems[] = "<{$tag}> element";
        }

        if ($tag === 'iframe' && ! str_starts_with((string) $element->getAttribute('src'), 'https://www.youtube-nocookie.com/embed/')) {
            $problems[] = 'foreign iframe';
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $attribute->value));

            if (str_starts_with($name, 'on') || $name === 'formaction' || $name === 'srcdoc') {
                $problems[] = "{$name} attribute on <{$tag}>";
            }

            if (in_array($name, ['href', 'src', 'poster', 'action', 'xlink:href'], true) && preg_match('/^(javascript|data|vbscript):/', $value) === 1) {
                $problems[] = "{$value} in {$name}";
            }

            if ($name === 'style' && ! preg_match('/^flex-basis:\d+%$/', $value)) {
                $problems[] = "style {$value}";
            }
        }
    }

    return $problems;
}

it('never outputs active content from user text', function (string $payload) {
    $html = app(BlockRenderer::class)->render(blocksWithPayload($payload));

    expect(activeContent($html))->toBe([]);
})->with([
    'script tag' => '<script>alert(document.cookie)</script>',
    'img onerror' => '<img src=x onerror=alert(1)>',
    'svg onload' => '<svg/onload=alert(1)>',
    'attribute break out' => '" onmouseover="alert(1)" x="',
    'single quote break out' => "' onfocus='alert(1)' autofocus='",
    'javascript link' => '<a href="javascript:alert(1)">win a prize</a>',
    'encoded javascript link' => '<a href="&#x6A;avascript:alert(1)">win</a>',
    'data link' => '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">win</a>',
    'iframe' => '<iframe src="https://evil.example"></iframe>',
    'style expression' => '<p style="background:url(javascript:alert(1))">x</p>',
    'closing tags' => '</p></figure></table><script>alert(1)</script>',
    'mutation xss' => '<noscript><p title="</noscript><img src=x onerror=alert(1)>">',
]);

it('finds active content when it is there', function () {
    expect(activeContent('<p onclick="x()">Hi</p><a href=" javascript:alert(1)">x</a><script></script><iframe src="https://evil.example"></iframe>'))
        ->toBe(['onclick attribute on <p>', 'javascript:alert(1) in href', '<script> element', 'foreign iframe']);
});

it('keeps the text of a normal article intact', function () {
    $html = app(BlockRenderer::class)->render(blocksWithPayload('Pastéis de nata & port wine'));

    expect(substr_count($html, 'Pastéis de nata &amp; port wine'))->toBeGreaterThan(10);
});
