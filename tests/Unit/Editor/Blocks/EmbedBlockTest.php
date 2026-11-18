<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\EmbedBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named embed', function () {
    expect(app(EmbedBlock::class)->name())->toBe('embed');
});

it('renders a privacy friendly youtube player', function () {
    expect($this->renderer->renderBlock(['type' => 'embed', 'attrs' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'A walk in Porto', 'caption' => 'Filmed in <em>May</em>']]))
        ->toBe('<figure class="verso-embed verso-embed--youtube"><div class="verso-embed__frame">'
            .'<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="A walk in Porto" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"'
            .' allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"'
            .' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups" allowfullscreen></iframe></div>'
            .'<figcaption>Filmed in <em>May</em></figcaption></figure>');
});

it('renders a vimeo player with do not track', function () {
    $html = $this->renderer->renderBlock(['type' => 'embed', 'attrs' => ['url' => 'https://vimeo.com/76979871']]);

    expect($html)->toContain('verso-embed--vimeo')
        ->toContain('src="https://player.vimeo.com/video/76979871?dnt=1"')
        ->toContain('title="Vimeo video"');
});

it('uses the provider name as the default title', function () {
    expect($this->renderer->renderBlock(['type' => 'embed', 'attrs' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']]))
        ->toContain('title="YouTube video"');
});

it('turns other web urls into a plain link', function () {
    expect($this->renderer->renderBlock(['type' => 'embed', 'attrs' => ['url' => 'https://www.dailymotion.com/video/x7tgad0']]))
        ->toBe('<figure class="verso-embed verso-embed--link"><a href="https://www.dailymotion.com/video/x7tgad0" rel="noopener nofollow">https://www.dailymotion.com/video/x7tgad0</a></figure>');
});

it('renders nothing for unsafe or empty urls', function (string $url) {
    expect($this->renderer->renderBlock(['type' => 'embed', 'attrs' => ['url' => $url]]))->toBe('');
})->with(['', 'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '/relative/path']);

it('escapes the title', function () {
    expect($this->renderer->renderBlock(['type' => 'embed', 'attrs' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => '"><script>alert(1)</script>']]))
        ->toContain('title="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->not->toContain('<script');
});

it('validates its attrs', function () {
    $rules = app(EmbedBlock::class)->rules();

    expect(Validator::make(['url' => 'https://vimeo.com/76979871'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['url' => 'javascript:alert(1)'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['url' => 'ftp://files.example.org/video.mp4'], $rules)->fails())->toBeTrue();
});
