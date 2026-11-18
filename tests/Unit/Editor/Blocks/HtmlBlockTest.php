<?php

use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Blocks\HtmlBlock;

it('is named html and needs the configured capability', function () {
    expect((new HtmlBlock)->name())->toBe('html')
        ->and((new HtmlBlock)->capability())->toBe('manage_settings');

    config(['verso.editor.html_capability' => 'manage_themes']);
    expect((new HtmlBlock)->capability())->toBe('manage_themes');

    config(['verso.editor.html_capability' => '']);
    expect((new HtmlBlock)->capability())->toBeNull();
});

it('prints raw html as it is', function () {
    $html = '<div class="booking-widget" data-hotel="ribeira"><iframe src="https://booking.example/widget"></iframe></div>';

    expect(app(BlockRenderer::class)->renderBlock(['type' => 'html', 'content' => $html]))->toBe($html);
});
