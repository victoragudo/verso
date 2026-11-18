<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Media\Models\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AiAssistantFixtures as Ai;
use Verso\Plugins\AiAssistant\Models\AiUsage;

beforeEach(function () {
    Ai::enable();
    $this->editor = User::factory()->editor()->create(['name' => 'Marta Ibáñez']);
});

// The text of the user message sent to the API.
function sentText(Request $request): string
{
    $content = $request['messages'][0]['content'];

    return (string) collect($content)->firstWhere('type', 'text')['text'];
}

$lisbonBlocks = [
    ['id' => 'b_intro1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Lisbon rewards slow walkers with <strong>tiled facades</strong> and river views.</p>', 'children' => []],
    ['id' => 'b_head01', 'type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Where to eat', 'children' => []],
    ['id' => 'b_list01', 'type' => 'list', 'attrs' => ['ordered' => false], 'content' => '<ul><li><p>Time Out Market</p></li><li><p>A tasca in Alfama</p></li></ul>', 'children' => []],
];

it('drafts an outline from the title as heading and list blocks', function () {
    Ai::fake(Ai::jsonReply(['sections' => [
        ['heading' => 'Why Lisbon in autumn', 'points' => ['Mild weather', 'Fewer crowds']],
        ['heading' => 'Three days, three neighbourhoods', 'points' => ['Alfama', 'Baixa & Chiado', 'Belém']],
    ]]));

    $response = $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.outline'), ['title' => 'Three slow days in Lisbon'])
        ->assertOk()
        ->assertJsonPath('model', 'claude-sonnet-5-5');

    $blocks = $response->json('result.blocks');

    expect($blocks)->toHaveCount(4)
        ->and($blocks[0])->toMatchArray(['type' => 'heading', 'attrs' => ['level' => 2], 'content' => 'Why Lisbon in autumn'])
        ->and($blocks[1])->toMatchArray(['type' => 'list', 'content' => '<ul><li><p>Mild weather</p></li><li><p>Fewer crowds</p></li></ul>'])
        ->and($blocks[3]['content'])->toContain('<li><p>Baixa &amp; Chiado</p></li>')
        ->and($blocks[0]['id'])->toMatch('/^b_[a-z0-9]{8}$/');

    Http::assertSent(fn (Request $request): bool => str_contains(sentText($request), 'Title: Three slow days in Lisbon')
        && $request['output_config']['format']['type'] === 'json_schema'
        && $request['output_config']['format']['schema']['required'] === ['sections']);
});

it('adds the author notes to the outline request', function () {
    Ai::fake(Ai::jsonReply(['sections' => [['heading' => 'Getting around', 'points' => ['Tram 28']]]]));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.outline'), ['title' => 'Three slow days in Lisbon', 'notes' => 'Mention the night trains from Porto.'])
        ->assertOk();

    Http::assertSent(fn (Request $request): bool => str_contains(sentText($request), 'Mention the night trains from Porto.'));
});

it('needs a title for the outline', function () {
    Http::fake();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.outline'), ['title' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');

    Http::assertNothingSent();
});

it('rejects an outline answer with no sections', function () {
    Ai::fake(Ai::jsonReply(['sections' => []]));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.outline'), ['title' => 'Three slow days in Lisbon'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The AI provider sent an answer Verso could not read. Try again.');
});

it('improves the selected text', function () {
    Ai::fake(Ai::reply('The old lighthouse keeper still climbs the 112 steps every evening.'));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.rewrite'), [
            'text' => 'The old lighthouse keeper he still climb the 112 steps every evenings.',
            'mode' => 'improve',
        ])
        ->assertOk()
        ->assertJsonPath('result.text', 'The old lighthouse keeper still climbs the 112 steps every evening.');

    Http::assertSent(fn (Request $request): bool => str_contains($request['system'], 'fix grammar')
        && sentText($request) === 'The old lighthouse keeper he still climb the 112 steps every evenings.');
});

it('shortens the selected text', function () {
    Ai::fake(Ai::reply('The keeper climbs 112 steps nightly.'));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.rewrite'), [
            'text' => 'Every single evening, without fail, the keeper climbs all of the 112 steps up to the lamp room.',
            'mode' => 'shorten',
        ])
        ->assertOk()
        ->assertJsonPath('result.text', 'The keeper climbs 112 steps nightly.');

    Http::assertSent(fn (Request $request): bool => str_contains($request['system'], 'shorter'));
});

it('only knows the improve and shorten modes', function () {
    Http::fake();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => 'A quiet harbor.', 'mode' => 'pirate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mode');

    Http::assertNothingSent();
});

it('refuses texts longer than the limit without calling the API', function () {
    config(['ai-assistant.limits.max_input_chars' => 40]);
    Http::fake();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => str_repeat('Long harbor story. ', 5), 'mode' => 'improve'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');

    Http::assertNothingSent();
});

it('writes an excerpt from the title and blocks', function () use ($lisbonBlocks) {
    Ai::fake(Ai::reply('"Three unhurried days of tiles, trams and tascas in Lisbon."'));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.excerpt'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks])
        ->assertOk()
        ->assertJsonPath('result.excerpt', 'Three unhurried days of tiles, trams and tascas in Lisbon.');

    Http::assertSent(function (Request $request): bool {
        $text = sentText($request);

        return str_contains($text, 'Title: Three slow days in Lisbon')
            && str_contains($text, 'Lisbon rewards slow walkers with tiled facades and river views.')
            && str_contains($text, '## Where to eat')
            && str_contains($text, "Time Out Market\nA tasca in Alfama")
            && ! str_contains($text, '<strong>');
    });
});

it('needs a title or content for the excerpt', function () {
    Http::fake();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.excerpt'), ['title' => '', 'blocks' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('blocks');

    Http::assertNothingSent();
});

it('suggests an SEO title and meta description', function () use ($lisbonBlocks) {
    Ai::fake(Ai::jsonReply([
        'title' => 'Lisbon in 3 Slow Days: Tiles, Trams and Tascas',
        'description' => 'A relaxed three day Lisbon plan: Alfama walks, river views and where locals eat.',
    ]));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.seo'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks])
        ->assertOk()
        ->assertJsonPath('result.title', 'Lisbon in 3 Slow Days: Tiles, Trams and Tascas')
        ->assertJsonPath('result.description', 'A relaxed three day Lisbon plan: Alfama walks, river views and where locals eat.');

    Http::assertSent(fn (Request $request): bool => $request['output_config']['format']['schema']['required'] === ['title', 'description']);
});

it('keeps SEO suggestions inside the site limits', function () use ($lisbonBlocks) {
    config(['verso.seo.title_max_length' => 20, 'verso.seo.description_max_length' => 30]);
    Ai::fake(Ai::jsonReply(['title' => str_repeat('Lisbon ', 10), 'description' => str_repeat('Tiles and trams. ', 10)]));

    $response = $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.seo'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks])
        ->assertOk();

    expect(mb_strlen($response->json('result.title')))->toBeLessThanOrEqual(20)
        ->and(mb_strlen($response->json('result.description')))->toBeLessThanOrEqual(30);
});

it('writes alt text for a library image and sends it as base64', function () {
    Storage::fake('public');
    Storage::disk('public')->put('2026/11/fishing-boats.png', 'png-bytes-of-fishing-boats');
    $media = Media::factory()->create([
        'disk' => 'public',
        'path' => '2026/11/fishing-boats.png',
        'filename' => 'fishing-boats.png',
        'mime_type' => 'image/png',
        'size' => 26,
        'alt' => null,
    ]);

    Ai::fake(Ai::reply('Blue fishing boats moored side by side in a small harbor at sunset.'));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.alt-text'), ['media_id' => $media->id])
        ->assertOk()
        ->assertJsonPath('result.alt', 'Blue fishing boats moored side by side in a small harbor at sunset.');

    Http::assertSent(fn (Request $request): bool => $request['messages'][0]['content'][0] === [
        'type' => 'image',
        'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode('png-bytes-of-fishing-boats')],
    ] && str_contains(sentText($request), 'fishing-boats.png'));

    expect($media->fresh()->alt)->toBeNull();
});

it('does not describe files that are not images', function () {
    Http::fake();
    $media = Media::factory()->document()->create();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.alt-text'), ['media_id' => $media->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The AI provider did not accept the request: only JPEG, PNG, GIF and WebP images can be described.');

    Http::assertNothingSent();
});

it('does not send images bigger than the limit', function () {
    config(['ai-assistant.limits.max_image_bytes' => 1024 * 1024]);
    Http::fake();
    $media = Media::factory()->create(['size' => 3 * 1024 * 1024]);

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.alt-text'), ['media_id' => $media->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The image is too big for the assistant (more than 1 MB).');

    Http::assertNothingSent();
});

it('reports a missing image file', function () {
    Storage::fake('public');
    Http::fake();
    $media = Media::factory()->create(['disk' => 'public', 'size' => 2048]);

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.alt-text'), ['media_id' => $media->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The AI provider did not accept the request: the image file is missing.');

    Http::assertNothingSent();
});

it('needs an existing media id for alt text', function () {
    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.alt-text'), ['media_id' => 9999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media_id');
});

it('translates blocks and keeps their structure', function () use ($lisbonBlocks) {
    Ai::fake(Ai::jsonReply([
        'title' => 'Tres días tranquilos en Lisboa',
        'segments' => [
            '<p>Lisboa premia a quien camina despacio con <strong>fachadas de azulejos</strong> y vistas al río.</p>',
            'Dónde comer',
            '<ul><li><p>Time Out Market</p></li><li><p>Una tasca en Alfama</p></li></ul>',
        ],
    ]));

    $response = $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.translate'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks, 'locale' => 'es'])
        ->assertOk()
        ->assertJsonPath('result.title', 'Tres días tranquilos en Lisboa')
        ->assertJsonPath('result.locale', 'es');

    $blocks = $response->json('result.blocks');

    expect(array_column($blocks, 'id'))->toBe(['b_intro1', 'b_head01', 'b_list01'])
        ->and(array_column($blocks, 'type'))->toBe(['paragraph', 'heading', 'list'])
        ->and($blocks[1]['attrs'])->toBe(['level' => 2])
        ->and($blocks[1]['content'])->toBe('Dónde comer')
        ->and($blocks[2]['content'])->toContain('Una tasca en Alfama');

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode(sentText($request), true);

        return str_contains($request['system'], 'Spanish (es)')
            && $payload['title'] === 'Three slow days in Lisbon'
            && count($payload['segments']) === 3;
    });
});

it('fails the translation when pieces are missing', function () use ($lisbonBlocks) {
    Ai::fake(Ai::jsonReply(['title' => 'Tres días en Lisboa', 'segments' => ['Solo una parte']]));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.translate'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks, 'locale' => 'es'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The translation lost some text pieces. Try again.');
});

it('only translates to the configured languages', function () use ($lisbonBlocks) {
    Http::fake();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.translate'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks, 'locale' => 'tlh'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');

    Http::assertNothingSent();
});

it('refuses to translate entries with too many pieces', function () use ($lisbonBlocks) {
    config(['ai-assistant.limits.max_translation_segments' => 2]);
    Http::fake();

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.translate'), ['title' => 'Three slow days in Lisbon', 'blocks' => $lisbonBlocks, 'locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The entry has too many text pieces to translate at once (more than 2).');

    Http::assertNothingSent();
});

it('never saves the entry, only returns a preview', function () use ($lisbonBlocks) {
    $entry = Entry::factory()->create(['title' => 'Three slow days in Lisbon', 'excerpt' => 'Original excerpt about Lisbon.', 'blocks' => $lisbonBlocks]);
    Ai::fake(Ai::reply('A brand new excerpt.'));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.excerpt'), ['title' => $entry->title, 'blocks' => $entry->blocks])
        ->assertOk();

    $fresh = $entry->fresh();

    expect($fresh->excerpt)->toBe('Original excerpt about Lisbon.')
        ->and($fresh->blocks)->toBe($lisbonBlocks)
        ->and($fresh->revisions()->count())->toBe($entry->revisions()->count());
});

it('logs the tokens of each answer for the user and the day', function () {
    $this->travelTo('2026-11-17 09:30:00');
    Ai::fake(Ai::reply('The keeper climbs 112 steps nightly.', 210, 35));

    $this->actingAs($this->editor)->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => 'A long story about the keeper.', 'mode' => 'shorten'])->assertOk();
    $this->actingAs($this->editor)->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => 'Another long story.', 'mode' => 'shorten'])->assertOk();

    $usage = AiUsage::query()->sole();

    expect($usage->user_id)->toBe($this->editor->id)
        ->and($usage->day->toDateString())->toBe('2026-11-17')
        ->and($usage->requests)->toBe(2)
        ->and($usage->input_tokens)->toBe(420)
        ->and($usage->output_tokens)->toBe(70)
        ->and($usage->totalTokens())->toBe(490);
});

it('logs nothing when the provider fails', function () {
    config(['ai-assistant.http.retries' => 0]);
    Ai::fake(Ai::error('api_error', 'Internal server error'), 500);

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => 'A quiet harbor.', 'mode' => 'improve'])
        ->assertStatus(502);

    expect(AiUsage::query()->count())->toBe(0);
});

it('answers 504 when the provider times out', function () {
    config(['ai-assistant.http.retries' => 0]);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds'));

    $this->actingAs($this->editor)
        ->postJson(route('admin.ai-assistant.actions.rewrite'), ['text' => 'A quiet harbor.', 'mode' => 'improve'])
        ->assertStatus(504)
        ->assertJsonPath('message', 'The AI provider took too long to answer. Try again with a shorter text.');
});
