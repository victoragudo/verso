<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Slots\AdminSlot;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\AiAssistantFixtures as Ai;
use Tests\Support\SharedSlots;
use Verso\Plugins\AiAssistant\AiSettings;
use Verso\Plugins\AiAssistant\Models\AiUsage;

const BAKERY_BUSINESS = 'Family bakery in Harbor Lane. Sourdough, cinnamon buns and coffee from 7am.';

beforeEach(function () {
    Ai::enable();
    app(Settings::class)->setMany(['site.name' => 'Harbor Lane Bakery', 'site.tagline' => 'Bread baked at five, gone by noon']);
    $this->editor = User::factory()->editor()->create(['name' => 'Marta Ibáñez']);
});

// The welcome section of a bakery page: heading, paragraph, list and button.
function bakeryHero(): array
{
    return [
        'id' => 'b_hero0001',
        'type' => 'section',
        'attrs' => ['tag' => 'section', 'label' => 'Welcome', 'design' => ['spacing' => ['padding' => ['top' => 'var:spacing.x-large']]]],
        'content' => '',
        'children' => [
            ['id' => 'b_title001', 'type' => 'heading', 'attrs' => ['level' => 1], 'content' => 'Welcome to our shop', 'children' => []],
            ['id' => 'b_text0001', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>We sell <strong>good things</strong>.</p>', 'children' => []],
            ['id' => 'b_list0001', 'type' => 'list', 'attrs' => ['ordered' => false], 'content' => '<ul><li><p>Fresh</p></li><li><p>Local</p></li></ul>', 'children' => []],
            ['id' => 'b_photo001', 'type' => 'image', 'attrs' => ['mediaId' => 7, 'src' => '/storage/loaves.jpg', 'alt' => ''], 'content' => '', 'children' => []],
            ['id' => 'b_button01', 'type' => 'button', 'attrs' => ['label' => 'Learn more', 'url' => '/menu', 'style' => 'primary'], 'content' => '', 'children' => []],
        ],
    ];
}

// The JSON payload the helper sent in the user message.
function sentPayload(Request $request): array
{
    return (array) json_decode((string) $request['messages'][0]['content'][0]['text'], true);
}

function writeSection(User $user, array $data = []): TestResponse
{
    return test()->actingAs($user)->postJson(route('admin.ai-assistant.actions.section-text'), [
        'section' => bakeryHero(),
        'business' => BAKERY_BUSINESS,
        ...$data,
    ]);
}

function suggestHeadlines(User $user, array $data = []): TestResponse
{
    return test()->actingAs($user)->postJson(route('admin.ai-assistant.actions.headlines'), [
        'headline' => 'Welcome to our shop',
        'context' => 'We sell good things.',
        'business' => BAKERY_BUSINESS,
        ...$data,
    ]);
}

describe('section texts', function () {
    it('writes new texts into a copy of the section and keeps its structure', function () {
        Ai::fake(Ai::jsonReply(['pieces' => [
            'Sourdough worth crossing the harbor for',
            '<p>We bake <strong>every morning at five</strong>.</p>',
            '<ul><li><p>Slow sourdough</p></li><li><p>Cinnamon buns</p></li></ul>',
            'See today\'s bread',
        ]]));

        $section = writeSection($this->editor)
            ->assertOk()
            ->assertJsonPath('model', 'claude-sonnet-5-5')
            ->json('result.section');

        $original = bakeryHero();

        expect($section['id'])->toBe('b_hero0001')
            ->and($section['attrs'])->toBe($original['attrs'])
            ->and(array_column($section['children'], 'id'))->toBe(array_column($original['children'], 'id'))
            ->and(array_column($section['children'], 'type'))->toBe(array_column($original['children'], 'type'))
            ->and($section['children'][0]['content'])->toBe('Sourdough worth crossing the harbor for')
            ->and($section['children'][1]['content'])->toBe('<p>We bake <strong>every morning at five</strong>.</p>')
            ->and($section['children'][2]['attrs'])->toBe(['ordered' => false])
            ->and($section['children'][3]['attrs']['src'])->toBe('/storage/loaves.jpg')
            ->and($section['children'][4]['attrs'])->toBe(['label' => 'See today\'s bread', 'url' => '/menu', 'style' => 'primary']);

        Http::assertSent(function (Request $request): bool {
            $payload = sentPayload($request);

            return $payload['business'] === BAKERY_BUSINESS
                && $payload['site'] === ['name' => 'Harbor Lane Bakery', 'tagline' => 'Bread baked at five, gone by noon']
                && $payload['section'] === 'Welcome'
                && count($payload['pieces']) === 4
                && $payload['pieces'][3] === 'Learn more'
                && $request['output_config']['format']['schema']['required'] === ['pieces'];
        });

        expect(AiUsage::query()->sole()->requests)->toBe(1);
    });

    it('refuses an answer that lost text pieces', function () {
        Ai::fake(Ai::jsonReply(['pieces' => ['Only one piece']]));

        writeSection($this->editor)
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI helper lost some text pieces. Try again.');
    });

    it('sends nothing for a section without text', function () {
        Http::fake();
        $empty = ['id' => 'b_empty001', 'type' => 'section', 'attrs' => [], 'content' => '', 'children' => [
            ['id' => 'b_photo002', 'type' => 'image', 'attrs' => ['mediaId' => 7, 'src' => '/storage/loaves.jpg'], 'content' => '', 'children' => []],
        ]];

        writeSection($this->editor, ['section' => $empty])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This section has no text to write yet.');

        Http::assertNothingSent();
    });

    it('sends nothing for a section with too many texts', function () {
        Http::fake();
        config(['ai-assistant.limits.max_section_pieces' => 3]);

        writeSection($this->editor)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This section has too many texts for the helper (more than 3). Split it into smaller sections.');

        Http::assertNothingSent();
    });

    it('needs a business description and a section', function () {
        Http::fake();

        writeSection($this->editor, ['business' => '', 'section' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business' => 'Tell the helper in a few words what your business does.', 'section']);

        Http::assertNothingSent();
    });

    it('turns a busy provider into a plain message', function () {
        config(['ai-assistant.http.retries' => 0]);
        Ai::fake(Ai::error('overloaded_error', 'Overloaded'), 529);

        writeSection($this->editor)
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI provider is busy right now. Try again in a moment.');
    });
});

describe('headlines', function () {
    it('suggests distinct headlines clipped to the limits', function () {
        config(['ai-assistant.targets.headline_count' => 3, 'ai-assistant.targets.headline_chars' => 40]);
        Ai::fake(Ai::jsonReply(['headlines' => [
            'Sourdough worth crossing the harbor for',
            'Sourdough worth crossing the harbor for',
            '"Warm bread from five in the morning, every single day of the week"',
            'Your corner bakery on Harbor Lane',
            'Coffee and cinnamon buns by the water',
        ]]));

        $headlines = suggestHeadlines($this->editor)->assertOk()->json('result.headlines');

        expect($headlines)->toBe([
            'Sourdough worth crossing the harbor for',
            'Warm bread from five in the morning, eve',
            'Your corner bakery on Harbor Lane',
        ]);

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['messages'][0]['content'][0]['text'], 'Current headline: Welcome to our shop')
            && str_contains((string) $request['system'], '3 to 3 different headlines')
            && $request['output_config']['format']['schema']['required'] === ['headlines']);
    });

    it('works without a current headline', function () {
        Ai::fake(Ai::jsonReply(['headlines' => ['Fresh bread on Harbor Lane']]));

        suggestHeadlines($this->editor, ['headline' => null, 'context' => null])->assertOk();

        Http::assertSent(fn (Request $request): bool => str_contains((string) $request['messages'][0]['content'][0]['text'], 'Current headline: (none yet)'));
    });

    it('refuses an answer without headlines', function () {
        Ai::fake(Ai::jsonReply(['headlines' => ['', '   ']]));

        suggestHeadlines($this->editor)
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI provider sent an answer Verso could not read. Try again.');
    });

    it('refuses a business description over the limit', function () {
        Http::fake();
        config(['ai-assistant.limits.max_business_chars' => 20]);

        suggestHeadlines($this->editor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business' => 'Keep the description of your business shorter (at most 20 characters).']);

        Http::assertNothingSent();
    });
});

describe('guard and permissions', function () {
    it('sends nothing when the plugin is off or has no key', function (Closure $turnOff, string $message) {
        Http::fake();
        $turnOff();

        writeSection($this->editor)->assertStatus(503)->assertJsonPath('message', $message);
        suggestHeadlines($this->editor)->assertStatus(503)->assertJsonPath('message', $message);

        Http::assertNothingSent();
    })->with([
        'plugin off' => [fn () => app(PluginManager::class)->disable('ai-assistant'), 'The AI assistant plugin is not enabled.'],
        'no key' => [fn () => app(AiSettings::class)->forgetApiKey(), 'Add an API key on the AI assistant settings page first.'],
    ]);

    it('counts the helper actions in the rate limit', function () {
        app(AiSettings::class)->save(['model' => 'claude-sonnet-5-5', 'max_tokens' => 4000, 'rate_limit' => 1]);
        Ai::fake(Ai::jsonReply(['headlines' => ['Fresh bread on Harbor Lane']]));

        suggestHeadlines($this->editor)->assertOk();

        writeSection($this->editor)
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'You have used all your AI requests for now. Try again in 60 min.');

        Http::assertSentCount(1);
    });

    it('forbids the helper to authors', function (string $route, string $method) {
        Http::fake();

        $this->actingAs(User::factory()->author()->create())
            ->json($method, route($route), ['business' => BAKERY_BUSINESS])
            ->assertForbidden();

        Http::assertNothingSent();
    })->with([
        ['admin.ai-assistant.actions.section-text', 'POST'],
        ['admin.ai-assistant.actions.headlines', 'POST'],
        ['admin.ai-assistant.business.update', 'PUT'],
    ]);
});

describe('business description', function () {
    it('starts from the search description, then the tagline', function () {
        $settings = app(AiSettings::class);

        expect($settings->businessDescription())->toBe('Bread baked at five, gone by noon');

        app(Settings::class)->set('seo.default_description', 'Sourdough and coffee in Harbor Lane since 1998.');

        expect($settings->businessDescription())->toBe('Sourdough and coffee in Harbor Lane since 1998.');
    });

    it('saves the description once for every page', function () {
        $this->actingAs($this->editor)
            ->putJson(route('admin.ai-assistant.business.update'), ['business' => '  '.BAKERY_BUSINESS.'  '])
            ->assertOk()
            ->assertJsonPath('result.business', BAKERY_BUSINESS);

        expect(app(Settings::class)->get(AiSettings::BUSINESS_DESCRIPTION))->toBe(BAKERY_BUSINESS)
            ->and(app(AiSettings::class)->businessDescription())->toBe(BAKERY_BUSINESS);
    });

    it('falls back to the tagline when the description is cleared', function () {
        $this->actingAs($this->editor)
            ->putJson(route('admin.ai-assistant.business.update'), ['business' => null])
            ->assertOk()
            ->assertJsonPath('result.business', 'Bread baked at five, gone by noon');
    });
});

describe('page editor props', function () {
    beforeEach(function () {
        $this->page = Entry::factory()->create(['type' => 'page', 'title' => 'Home', 'blocks' => [bakeryHero()]]);
    });

    it('shares the helper with editors on the page editor', function () {
        $response = $this->actingAs($this->editor)->get(route('admin.easy.pages.edit', $this->page))->assertOk();

        $item = SharedSlots::item($response, AdminSlot::PageEditor, 'ai-helper');

        expect($item['component'])->toBe('ai-assistant.page-helper')
            ->and($item['props']['ready'])->toBeTrue()
            ->and($item['props']['settingsUrl'])->toBeNull()
            ->and($item['props']['endpoints'])->toBe([
                'sectionText' => route('admin.ai-assistant.actions.section-text'),
                'headlines' => route('admin.ai-assistant.actions.headlines'),
                'translate' => route('admin.ai-assistant.actions.translate'),
                'altText' => route('admin.ai-assistant.actions.alt-text'),
                'business' => route('admin.ai-assistant.business.update'),
            ])
            ->and($item['props']['business'])->toBe('Bread baked at five, gone by noon')
            ->and($item['props']['businessMax'])->toBe(1000)
            ->and($item['props']['maxImages'])->toBe(12)
            ->and($item['props']['locales'][1])->toBe(['code' => 'es', 'name' => 'Spanish']);
    });

    it('tells admins where to add a key when it is missing', function () {
        app(AiSettings::class)->forgetApiKey();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.easy.pages.edit', $this->page))->assertOk();
        $props = SharedSlots::item($response, AdminSlot::PageEditor, 'ai-helper')['props'];

        expect($props['ready'])->toBeFalse()
            ->and($props['settingsUrl'])->toBe(route('admin.ai-assistant.index'));
    });

    it('shares nothing with users who may not use the assistant', function () {
        $author = User::factory()->author()->create();
        $own = Entry::factory()->create(['type' => 'page', 'title' => 'About us', 'author_id' => $author->id, 'blocks' => [bakeryHero()]]);

        $response = $this->actingAs($author)->get(route('admin.easy.pages.edit', $own))->assertOk();

        expect(SharedSlots::item($response, AdminSlot::PageEditor, 'ai-helper'))->toBeNull();
    });

    it('shares the helper only on the page editor', function () {
        $response = $this->actingAs($this->editor)->get(route('admin.dashboard'))->assertOk();

        expect(SharedSlots::items($response, AdminSlot::PageEditor))->toBe([]);
    });
});
