<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\VideoBlock;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->renderer = app(BlockRenderer::class);
});

it('is named video', function () {
    expect((new VideoBlock)->name())->toBe('video');
});

it('renders a video with controls by default', function () {
    expect($this->renderer->renderBlock(['type' => 'video', 'attrs' => ['src' => '/storage/tram-ride.mp4', 'poster' => '/storage/tram.jpg', 'caption' => 'Tram 28']]))
        ->toBe('<figure class="verso-video"><video src="/storage/tram-ride.mp4" poster="/storage/tram.jpg" controls playsinline preload="metadata"></video><figcaption>Tram 28</figcaption></figure>');
});

it('mutes autoplaying videos', function () {
    expect($this->renderer->renderBlock(['type' => 'video', 'attrs' => ['src' => '/v.mp4', 'autoplay' => true, 'loop' => true, 'controls' => false]]))
        ->toBe('<figure class="verso-video"><video src="/v.mp4" autoplay loop muted playsinline preload="metadata"></video></figure>');
});

it('drops an unsafe poster and renders nothing for an unsafe source', function () {
    expect($this->renderer->renderBlock(['type' => 'video', 'attrs' => ['src' => '/v.mp4', 'poster' => 'javascript:alert(1)']]))->not->toContain('poster')
        ->and($this->renderer->renderBlock(['type' => 'video', 'attrs' => ['src' => 'javascript:alert(1)']]))->toBe('');
});

it('validates its attrs', function () {
    $rules = (new VideoBlock)->rules();

    expect(Validator::make(['src' => '/v.mp4', 'autoplay' => true], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['poster' => 'data:image/png;base64,AAAA'], $rules)->fails())->toBeTrue();
});
