<?php

use App\Verso\Core\Settings\Settings;
use Tests\Support\AiAssistantFixtures as Ai;
use Verso\Plugins\AiAssistant\AiResponse;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Exceptions\AiProviderError;
use Verso\Plugins\AiAssistant\Exceptions\AiRateLimited;
use Verso\Plugins\AiAssistant\Support\BlockText;
use Verso\Plugins\AiAssistant\Support\BlockTranslation;

beforeEach(function () {
    Ai::enable(withKey: false);
});

describe('BlockTranslation', function () {
    beforeEach(function () {
        $this->translation = app(BlockTranslation::class);
        $this->blocks = [
            ['id' => 'b_p1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>The night market opens at <em>seven</em>.</p>', 'children' => []],
            ['id' => 'b_img', 'type' => 'image', 'attrs' => ['mediaId' => 12, 'src' => '/storage/stall.jpg', 'alt' => 'A noodle stall', 'caption' => 'Stall 14, Taipei'], 'children' => []],
            ['id' => 'b_code', 'type' => 'code', 'attrs' => ['language' => 'php'], 'content' => 'echo "hello";', 'children' => []],
            ['id' => 'b_cols', 'type' => 'columns', 'attrs' => [], 'children' => [
                ['id' => 'b_col', 'type' => 'column', 'attrs' => [], 'children' => [
                    ['id' => 'b_q', 'type' => 'quote', 'attrs' => ['citation' => 'A street vendor'], 'content' => '<p>Come hungry.</p>', 'children' => []],
                ]],
            ]],
            ['id' => 'b_tbl', 'type' => 'table', 'attrs' => ['header' => true, 'rows' => [['Dish', 'Price'], ['Oyster omelette', '']]], 'children' => []],
            ['id' => 'b_btn', 'type' => 'button', 'attrs' => ['url' => 'https://example.org/map', 'label' => 'Open the map', 'style' => 'primary'], 'children' => []],
            ['id' => 'b_empty', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p></p>', 'children' => []],
        ];
    });

    it('collects text pieces in reading order and skips code, urls and empty text', function () {
        expect($this->translation->segments($this->blocks))->toBe([
            '<p>The night market opens at <em>seven</em>.</p>',
            'A noodle stall',
            'Stall 14, Taipei',
            '<p>Come hungry.</p>',
            'A street vendor',
            'Dish',
            'Price',
            'Oyster omelette',
            'Open the map',
        ]);
    });

    it('puts translations back in the same places', function () {
        $translated = $this->translation->apply($this->blocks, [
            '<p>El mercado nocturno abre a las <em>siete</em>.</p>',
            'Un puesto de fideos',
            'Puesto 14, Taipéi',
            '<p>Ven con hambre.</p>',
            'Un vendedor ambulante',
            'Plato',
            'Precio',
            'Tortilla de ostras',
            'Abrir el mapa',
        ]);

        expect($translated[0]['content'])->toBe('<p>El mercado nocturno abre a las <em>siete</em>.</p>')
            ->and($translated[1]['attrs'])->toBe(['mediaId' => 12, 'src' => '/storage/stall.jpg', 'alt' => 'Un puesto de fideos', 'caption' => 'Puesto 14, Taipéi'])
            ->and($translated[2])->toBe($this->blocks[2])
            ->and($translated[3]['children'][0]['children'][0]['attrs']['citation'])->toBe('Un vendedor ambulante')
            ->and($translated[3]['children'][0]['children'][0]['content'])->toBe('<p>Ven con hambre.</p>')
            ->and($translated[4]['attrs']['rows'])->toBe([['Plato', 'Precio'], ['Tortilla de ostras', '']])
            ->and($translated[5]['attrs'])->toBe(['url' => 'https://example.org/map', 'label' => 'Abrir el mapa', 'style' => 'primary'])
            ->and($translated[6])->toBe($this->blocks[6]);
    });

    it('keeps the ids, types and order of every block', function () {
        $translated = $this->translation->apply($this->blocks, array_fill(0, 9, 'x'));

        expect(array_column($translated, 'id'))->toBe(array_column($this->blocks, 'id'))
            ->and(array_column($translated, 'type'))->toBe(array_column($this->blocks, 'type'));
    });

    it('reads the translatable types and attributes from config', function () {
        config(['ai-assistant.translation.content_types' => ['heading'], 'ai-assistant.translation.attrs' => []]);

        expect(app(BlockTranslation::class)->segments($this->blocks))->toBe(['Dish', 'Price', 'Oyster omelette']);
    });
});

describe('BlockText', function () {
    it('turns blocks into readable plain text', function () {
        $text = app(BlockText::class)->plain([
            ['type' => 'heading', 'content' => 'Packing for the Camino'],
            ['type' => 'paragraph', 'content' => '<p>Bring <strong>two</strong> pairs of socks &amp; a light rain jacket.</p>'],
            ['type' => 'list', 'content' => '<ul><li><p>Blister plasters</p></li><li><p>Headlamp</p></li></ul>'],
            ['type' => 'html', 'content' => '<script>track()</script>'],
            ['type' => 'columns', 'children' => [['type' => 'column', 'children' => [['type' => 'paragraph', 'content' => '<p>Buen camino!</p>']]]]],
        ]);

        expect($text)->toBe("## Packing for the Camino\n\nBring two pairs of socks & a light rain jacket.\n\nBlister plasters\nHeadlamp\n\nBuen camino!");
    });

    it('ignores broken blocks', function () {
        expect(app(BlockText::class)->plain(['not a block', ['type' => 'paragraph', 'content' => ['nested']]]))->toBe('');
    });
});

describe('AiSettings', function () {
    it('stores the API key encrypted and reads it back', function () {
        $settings = app(AiSettings::class);
        $settings->save(['api_key' => '  '.Ai::API_KEY.'  ', 'model' => 'claude-opus-5-5', 'max_tokens' => 8000, 'rate_limit' => 12]);

        $stored = app(Settings::class)->get(AiSettings::API_KEY);

        expect($stored)->not->toContain('sk-ant')
            ->and($settings->apiKey())->toBe(Ai::API_KEY)
            ->and($settings->hasApiKey())->toBeTrue()
            ->and($settings->maskedApiKey())->toBe('••••••••Q9xZ')
            ->and($settings->model())->toBe('claude-opus-5-5')
            ->and($settings->maxTokens())->toBe(8000)
            ->and($settings->rateLimit())->toBe(12);
    });

    it('keeps the saved key when the form sends an empty key', function () {
        $settings = app(AiSettings::class);
        $settings->save(['api_key' => Ai::API_KEY, 'model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 30]);
        $settings->save(['api_key' => '', 'model' => 'claude-haiku-5-5', 'max_tokens' => 2000, 'rate_limit' => 10]);

        expect($settings->apiKey())->toBe(Ai::API_KEY)
            ->and($settings->model())->toBe('claude-haiku-5-5');
    });

    it('falls back to config defaults', function () {
        config(['ai-assistant.defaults.max_tokens' => 3000, 'ai-assistant.defaults.rate_limit' => 15]);
        $settings = app(AiSettings::class);

        expect($settings->hasApiKey())->toBeFalse()
            ->and($settings->maskedApiKey())->toBeNull()
            ->and($settings->model())->toBe('claude-sonnet-5-5')
            ->and($settings->maxTokens())->toBe(3000)
            ->and($settings->rateLimit())->toBe(15);
    });

    it('forgets the key', function () {
        $settings = app(AiSettings::class);
        $settings->save(['api_key' => Ai::API_KEY, 'model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 30]);
        $settings->forgetApiKey();

        expect($settings->hasApiKey())->toBeFalse();
    });
});

describe('AiResponse', function () {
    it('reads json answers', function () {
        expect((new AiResponse('{"title":"Sintra day trip"}', 10, 5))->json())->toBe(['title' => 'Sintra day trip']);
    });

    it('rejects answers that are not json objects', function (string $text) {
        expect(fn () => (new AiResponse($text, 10, 5))->json())->toThrow(AiProviderError::class);
    })->with(['Sure! Here is your title.', '"just a string"']);
});

it('rounds the rate limit wait up to whole minutes', function () {
    expect((new AiRateLimited(61))->getMessage())->toContain('2 min')
        ->and((new AiRateLimited(5))->getMessage())->toContain('1 min')
        ->and((new AiRateLimited(5))->status())->toBe(429);
});
