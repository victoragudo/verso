<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\CodeBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named code', function () {
    expect((new CodeBlock)->name())->toBe('code');
});

it('escapes the code', function () {
    expect($this->renderer->renderBlock(['type' => 'code', 'attrs' => ['language' => 'html'], 'content' => '<script>alert("hi")</script> & more']))
        ->toBe('<pre><code class="language-html">&lt;script&gt;alert("hi")&lt;/script&gt; &amp; more</code></pre>');
});

it('keeps new lines and spaces', function () {
    expect($this->renderer->renderBlock(['type' => 'code', 'content' => "if (\$open) {\n    serve();\n}"]))
        ->toBe("<pre><code>if (\$open) {\n    serve();\n}</code></pre>");
});

it('ignores unsafe language names', function () {
    expect($this->renderer->renderBlock(['type' => 'code', 'attrs' => ['language' => 'php" onclick="x'], 'content' => 'echo 1;']))
        ->toBe('<pre><code>echo 1;</code></pre>');
});

it('lowercases the language', function () {
    expect($this->renderer->renderBlock(['type' => 'code', 'attrs' => ['language' => 'TypeScript'], 'content' => 'let a = 1']))
        ->toBe('<pre><code class="language-typescript">let a = 1</code></pre>');
});

it('renders nothing when empty', function () {
    expect($this->renderer->renderBlock(['type' => 'code', 'content' => '']))->toBe('');
});

it('validates the language', function () {
    $rules = (new CodeBlock)->rules();

    expect(Validator::make(['language' => 'c++'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['language' => 'php"><script>'], $rules)->fails())->toBeTrue();
});
