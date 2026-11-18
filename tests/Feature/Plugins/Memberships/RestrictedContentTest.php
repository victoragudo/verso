<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Fields\Models\FieldGroupRecord;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\ContentRenderer;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;

require_once __DIR__.'/Helpers.php';

// A members only box with one paragraph inside.
function membersBox(string $plan = '', string $message = ''): array
{
    return ['id' => 'b_members', 'type' => 'members-only', 'attrs' => array_filter(['plan' => $plan, 'message' => $message]), 'content' => '', 'children' => [
        ['id' => 'b_inside', 'type' => 'paragraph', 'attrs' => [], 'content' => 'The secret trail starts behind the lighthouse.', 'children' => []],
    ]];
}

beforeEach(function () {
    PluginFixtures::boot('memberships');
    $this->plans = membershipPlans();
});

it('shows a restricted post to members of the plan', function () {
    $post = restrictedPost('reader');

    $this->actingAs(memberOf($this->plans['reader']))
        ->get($post->path())
        ->assertOk()
        ->assertSee('Sardine prices in Peniche')
        ->assertDontSee('verso-members-teaser');
});

it('shows the excerpt and a teaser to guests', function () {
    $post = restrictedPost('reader');

    $this->get($post->path())
        ->assertOk()
        ->assertSee('Why small harbors keep their prices low.')
        ->assertSee('This content is for members only.')
        ->assertSee('Log in')
        ->assertDontSee('Sardine prices in Peniche');
});

it('shows a teaser to members of a lower plan', function () {
    $post = restrictedPost('patron');

    $this->actingAs(memberOf($this->plans['reader']))
        ->get($post->path())
        ->assertSee('verso-members-teaser', false)
        ->assertDontSee('Sardine prices in Peniche');
});

it('keeps public posts open to everyone', function () {
    $post = restrictedPost('everyone');

    $this->get($post->path())->assertSee('Sardine prices in Peniche');
});

it('hides restricted content in the feed', function () {
    restrictedPost('members');

    $this->get('/feed')->assertOk()->assertDontSee('Sardine prices in Peniche');
});

it('hides custom fields of restricted entries in the API', function () {
    FieldGroupRecord::factory()->create(['fields' => [['name' => 'harbor_tip', 'label' => 'Harbor tip', 'type' => 'text']]]);
    $post = restrictedPost('members');
    $post->update(['meta' => [...$post->meta, 'fields' => ['harbor_tip' => 'Ask for the morning catch']]]);

    $guest = $this->getJson("/api/v1/entries/post/{$post->slug}")->assertOk();
    $member = $this->actingAs(memberOf($this->plans['reader']))->getJson("/api/v1/entries/post/{$post->slug}")->assertOk();

    expect($guest->getContent())->not->toContain('morning catch')
        ->and($member->json('data.fields.harbor_tip'))->toBe('Ask for the morning catch');
});

it('hides restricted content and blocks in the API', function () {
    $post = restrictedPost('members');

    $response = $this->getJson("/api/v1/entries/post/{$post->slug}")->assertOk();

    expect($response->json('data.blocks') ?? $response->json('blocks'))->toBe([])
        ->and(json_encode($response->json()))->not->toContain('Sardine prices')
        ->and($response->json('data.members_only') ?? $response->json('members_only'))->toBeTrue();
});

it('hides the children of a members only block from guests', function () {
    $entry = new Entry(['blocks' => [membersBox(message: 'Members get the full trail map.')]]);

    $html = app(ContentRenderer::class)->render($entry);

    expect($html)->toContain('Members get the full trail map.')
        ->not->toContain('secret trail');
});

it('shows the children of a members only block to members', function () {
    $this->actingAs(memberOf($this->plans['reader']));

    $html = app(ContentRenderer::class)->render(new Entry(['blocks' => [membersBox()]]));

    expect($html)->toBe('<div class="verso-members-only"><p>The secret trail starts behind the lighthouse.</p></div>');
});

it('checks the plan of a members only block', function () {
    $this->actingAs(memberOf($this->plans['reader']));

    expect(app(ContentRenderer::class)->render(new Entry(['blocks' => [membersBox('patron')]])))
        ->not->toContain('secret trail');
});

it('describes the block for the editor with its plans', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pluginBlocks', function ($blocks) {
                $block = collect($blocks)->firstWhere('name', 'members-only');

                return $block['children'] === true
                    && $block['fields'][0]['options'][1]['value'] === 'reader'
                    && $block['fields'][0]['options'][2]['value'] === 'patron';
            })
            ->where('pluginPanels.0.key', 'membership')
            ->where('pluginPanels.0.fields.0.options.0.value', 'everyone')
        );
});

it('saves a members only block and the access panel from the editor', function () {
    $this->actingAs(User::factory()->editor()->create())->post(route('admin.entries.store', 'post'), [
        'title' => 'Hidden beaches of the Alentejo',
        'status' => 'published',
        'blocks' => [membersBox('patron')],
        'meta' => ['membership' => ['plan' => 'reader']],
    ])->assertSessionHasNoErrors();

    $entry = Entry::firstWhere('slug', 'hidden-beaches-of-the-alentejo');

    expect($entry->meta['membership'])->toBe(['plan' => 'reader'])
        ->and($entry->blocks[0]['children'][0]['type'])->toBe('paragraph');
});

it('rejects unknown plans and nested members only blocks', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Hidden beaches of the Alentejo',
        'status' => 'draft',
        'meta' => ['membership' => ['plan' => 'gold-club']],
    ])->assertSessionHasErrors('meta.membership.plan');

    $nested = membersBox();
    $nested['children'] = [['id' => 'b_twice', 'type' => 'members-only', 'attrs' => [], 'content' => '', 'children' => []]];

    $this->actingAs($editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Hidden coves of the Algarve',
        'status' => 'draft',
        'blocks' => [$nested],
    ])->assertSessionHasErrors('blocks');
});

it('registers the block only once the plugin is on', function () {
    expect(app(PluginBlockRegistry::class)->find('members-only')?->label())->toBe('Members only');
});
