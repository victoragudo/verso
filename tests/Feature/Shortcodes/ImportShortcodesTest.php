<?php

use App\Verso\Importer\Content\ContentConverter;
use App\Verso\Importer\Content\MediaMap;
use App\Verso\Importer\Content\ShortcodeMapper;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PluginFixtures;

require_once __DIR__.'/../Plugins/Forms/Helpers.php';

function importContent(string $content): array
{
    return app(ContentConverter::class)->convert($content, 'wordpress:44', new MediaMap);
}

it('maps contact-form-7 to a Forms form when one with the imported title exists', function () {
    PluginFixtures::boot('forms');
    Mail::fake();
    bakeryContactForm();

    $blocks = importContent("Write to us.\n\n[contact-form-7 id=\"1234\" title=\"Bakery contact\"]");

    expect($blocks[1])->toMatchArray(['type' => 'form', 'attrs' => ['form' => 'bakery-contact', 'title' => '', 'text' => '']]);
});

it('leaves a notice paragraph when no imported form matches', function () {
    $blocks = importContent('[contact-form-7 id="88" title="Wholesale orders"]');

    expect($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['content'])->toBe('A Contact Form 7 form &quot;Wholesale orders&quot; (id 88) was here. Build it again under Forms and add the Form block here.');
});

it('maps gallery, embed and caption shortcodes to core blocks', function () {
    $blocks = importContent(implode("\n\n", [
        '[gallery ids="101,102"]',
        '[embed]https://www.youtube.com/watch?v=Kx3pQ8xL2aE[/embed]',
        '[caption id="attachment_101" width="640"]<img src="https://harbourlight.example/tram.jpg" alt="Tram"> Tram 28 at dawn[/caption]',
    ]));

    expect(array_column($blocks, 'type'))->toBe(['gallery', 'embed', 'image'])
        ->and($blocks[1]['attrs']['url'])->toBe('https://www.youtube.com/watch?v=Kx3pQ8xL2aE')
        ->and($blocks[2]['attrs']['caption'])->toBe('Tram 28 at dawn');
});

it('maps audio and video shortcodes', function () {
    $blocks = importContent("[audio mp3=\"https://harbourlight.example/fado-night.mp3\"]\n\n[video mp4=\"https://harbourlight.example/douro.mp4\" poster=\"https://harbourlight.example/douro.jpg\" loop=\"on\"][/video]");

    expect($blocks[0])->toMatchArray(['type' => 'html', 'content' => '<audio controls preload="none" src="https://harbourlight.example/fado-night.mp3"></audio>'])
        ->and($blocks[1]['type'])->toBe('video')
        ->and($blocks[1]['attrs'])->toBe(['src' => 'https://harbourlight.example/douro.mp4', 'poster' => 'https://harbourlight.example/douro.jpg', 'loop' => true]);
});

it('does not trust javascript URLs in audio shortcodes', function () {
    $blocks = importContent('[audio src="javascript:alert(1)"]');

    expect($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['content'])->toBe('[audio src="javascript:alert(1)"]');
});

it('converts Gutenberg shortcode blocks the same way', function () {
    $blocks = importContent('<!-- wp:shortcode -->[audio src="https://harbourlight.example/tide.mp3"]<!-- /wp:shortcode -->');

    expect($blocks[0]['type'])->toBe('html');
});

it('lists unknown shortcodes and keeps them as text', function () {
    $blocks = importContent("Our menu: [woocommerce_cart] and [[escaped]].\n\n[gallery ids=\"101\"]\n\n<pre><code>[not-a-shortcode]</code></pre>");

    expect(app(ShortcodeMapper::class)->unknown($blocks))->toBe(['woocommerce_cart'])
        ->and($blocks[0]['content'])->toContain('[woocommerce_cart]');
});
