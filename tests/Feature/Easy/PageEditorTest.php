<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryAbility;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Sections\SectionKit;
use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\EntryCapabilityFilter;
use App\Verso\Theme\Cache\PageCache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EasyFixtures;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false, 'verso.admin_bar.enabled' => true]);
    app()->forgetInstance(PageCache::class);

    $this->admin = User::factory()->admin()->create(['name' => 'Marta Oliveira']);
    $kit = app(SectionKit::class);
    $library = app(SectionLibrary::class);
    $this->blocks = [
        ...$library->build('hero', ['heading' => 'Fresh bread on Harbour Lane'], $kit),
        ...$library->build('faq', [], $kit),
    ];
    $this->page = Entry::factory()->page()->published()->by($this->admin)->create([
        'title' => 'Harbour Lane Bakery',
        'slug' => 'bakery',
        'blocks' => $this->blocks,
    ]);
});

function withHeading(array $blocks, string $heading): array
{
    $blocks[0]['children'][0]['children'][0]['content'] = $heading;

    return $blocks;
}

it('sends guests to the login page', function () {
    $this->get(route('admin.easy.pages.edit', $this->page))->assertRedirect(route('login'));
    $this->post(route('admin.easy.pages.frame', $this->page), ['blocks' => $this->blocks])->assertRedirect(route('login'));
    $this->put(route('admin.easy.pages.update', $this->page), ['blocks' => $this->blocks])->assertRedirect(route('login'));
    $this->getJson(route('admin.easy.sections.build', 'hero'))->assertUnauthorized();
});

it('opens the visual editor with the blocks, the ready sections and the screen sizes', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.easy.pages.edit', $this->page))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/easy/page-editor')
            ->where('entry.id', $this->page->id)
            ->where('entry.title', 'Harbour Lane Bakery')
            ->where('entry.typeLabel', 'Page')
            ->where('entry.status', 'published')
            ->where('entry.url', $this->page->url())
            ->where('entry.backUrl', $this->page->url())
            ->where('entry.advancedUrl', route('admin.entries.edit', ['contentType' => 'page', 'entry' => $this->page->id]))
            ->where('entry.canPublish', true)
            ->where('blocks.0.id', $this->blocks[0]['id'])
            ->where('sections.0.key', 'hero')
            ->where('previewWidths', config('verso.site_parts.preview_widths'))
            ->where('renderDelay', 250)
            ->where('undoSteps', 100)
            ->where('undoSeconds', 8)
            ->has('editor')
        );
});

it('only lets people who may change the entry use the editor', function () {
    $author = User::factory()->author()->create(['name' => 'Rui Almeida']);

    $this->actingAs($author)->get(route('admin.easy.pages.edit', $this->page))->assertForbidden();
    $this->actingAs($author)->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $this->blocks])->assertForbidden();
    $this->actingAs($author)->putJson(route('admin.easy.pages.update', $this->page), ['blocks' => []])->assertForbidden();

    expect($this->page->fresh()->blocks)->toBe($this->blocks);
});

it('respects plugins that deny the update', function () {
    Filters::add(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool => $ability === EntryAbility::Update ? false : $allowed);

    $this->actingAs($this->admin)->get(route('admin.easy.pages.edit', $this->page))->assertForbidden();
});

it('draws the real page from unsaved blocks with editor markers, never cached and never saved', function () {
    $blocks = withHeading($this->blocks, 'Sourdough every morning');

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $blocks])
        ->assertOk();

    $html = (string) $response->getContent();

    expect($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('Sourdough every morning')
        ->and($html)->toContain('data-verso-block="'.$blocks[0]['id'].'" data-verso-type="section"')
        ->and($html)->toContain('data-verso-type="heading"')
        ->and($html)->not->toContain('aria-label="Site administration"')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($this->page->fresh()->blocks)->toBe($this->blocks);

    $public = (string) $this->get('/bakery')->assertOk()->getContent();

    expect($public)->toContain('Fresh bread on Harbour Lane')
        ->and($public)->not->toContain('Sourdough every morning')
        ->and($public)->not->toContain('data-verso-block="');
});

it('draws the frame with the views of the active theme, like the public page', function () {
    app(Settings::class)->set('theme.active', 'ashgrove');

    $frame = (string) $this->actingAs($this->admin)
        ->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $this->blocks])
        ->assertOk()
        ->getContent();

    expect($frame)->toContain('<a class="brand"')
        ->and($frame)->toContain('Fresh bread on Harbour Lane');
});

it('rejects invalid blocks in the frame with a plain message', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => [['id' => 'b_x', 'type' => 'marquee', 'attrs' => [], 'content' => '', 'children' => []]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['blocks' => 'Block 1: the block type is unknown.']);
});

it('shows hidden sections faded in the editor and leaves them out of the public page', function () {
    $blocks = $this->blocks;
    $blocks[1]['attrs']['design']['visibility']['hidden'] = true;

    $frame = (string) $this->actingAs($this->admin)->postJson(route('admin.easy.pages.frame', $this->page), ['blocks' => $blocks])->getContent();

    expect($frame)->toContain('data-verso-block="'.$blocks[1]['id'].'" data-verso-type="section" data-verso-hidden')
        ->and($frame)->toContain('How can I contact you?');

    $this->actingAs($this->admin)->put(route('admin.easy.pages.update', $this->page), ['blocks' => $blocks])->assertRedirect();
    auth()->logout();

    $this->get('/bakery')->assertOk()
        ->assertSee('Fresh bread on Harbour Lane')
        ->assertDontSee('How can I contact you?');
});

it('saves the blocks through UpdateEntry with a revision and keeps the status', function () {
    $draft = Entry::factory()->page()->by($this->admin)->create(['title' => 'Summer menu', 'slug' => 'summer-menu', 'blocks' => $this->blocks]);
    $blocks = withHeading($this->blocks, 'Lemon tarts are back');

    $this->actingAs($this->admin)
        ->from(route('admin.easy.pages.edit', $draft))
        ->put(route('admin.easy.pages.update', $draft), ['blocks' => $blocks])
        ->assertRedirect(route('admin.easy.pages.edit', $draft))
        ->assertInertiaFlash('toast.message', 'Draft saved. Only you can see it.');

    $draft->refresh();

    expect($draft->blocks[0]['children'][0]['children'][0]['content'])->toBe('Lemon tarts are back')
        ->and($draft->status)->toBe(EntryStatus::Draft)
        ->and($draft->revisions()->count())->toBe(1)
        ->and($draft->revisions()->first()->blocks)->toBe($this->blocks);
});

it('publishes a draft when asked', function () {
    $draft = Entry::factory()->page()->by($this->admin)->create(['title' => 'Summer menu', 'slug' => 'summer-menu', 'blocks' => $this->blocks]);

    $this->actingAs($this->admin)
        ->put(route('admin.easy.pages.update', $draft), ['blocks' => $this->blocks, 'publish' => true])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Published. Visitors see your page now.');

    expect($draft->fresh()->status)->toBe(EntryStatus::Published);
});

it('saves a published page live', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.easy.pages.update', $this->page), ['blocks' => withHeading($this->blocks, 'Croissants on Sundays')])
        ->assertInertiaFlash('toast.message', 'Saved. Visitors see your changes now.');

    auth()->logout();
    $this->get('/bakery')->assertSee('Croissants on Sundays');
});

it('lets contributors save drafts but not publish', function () {
    $contributor = User::factory()->contributor()->create(['name' => 'Inês Duarte']);
    $draft = Entry::factory()->page()->by($contributor)->create(['title' => 'Our ovens', 'slug' => 'our-ovens', 'blocks' => $this->blocks]);

    $this->actingAs($contributor)->get(route('admin.easy.pages.edit', $draft))
        ->assertInertia(fn (Assert $page) => $page->where('entry.canPublish', false));

    $this->actingAs($contributor)->put(route('admin.easy.pages.update', $draft), ['blocks' => $this->blocks, 'publish' => true])->assertForbidden();
    $this->actingAs($contributor)->put(route('admin.easy.pages.update', $draft), ['blocks' => withHeading($this->blocks, 'Wood fired since 1962')])->assertRedirect();

    expect($draft->fresh()->status)->toBe(EntryStatus::Draft)
        ->and($draft->fresh()->blocks[0]['children'][0]['children'][0]['content'])->toBe('Wood fired since 1962');
});

it('rejects invalid blocks when saving and keeps the page', function (array $blocks, string $message) {
    $this->actingAs($this->admin)
        ->from(route('admin.easy.pages.edit', $this->page))
        ->put(route('admin.easy.pages.update', $this->page), ['blocks' => $blocks])
        ->assertRedirect(route('admin.easy.pages.edit', $this->page))
        ->assertSessionHasErrors(['blocks' => $message]);

    expect($this->page->fresh()->blocks)->toBe($this->blocks);
})->with([
    'unknown type' => [[['id' => 'b_x', 'type' => 'marquee', 'attrs' => [], 'content' => '', 'children' => []]], 'Block 1: the block type is unknown.'],
    'same id twice' => [[
        ['id' => 'b_twice', 'type' => 'heading', 'attrs' => [], 'content' => 'Bread', 'children' => []],
        ['id' => 'b_twice', 'type' => 'heading', 'attrs' => [], 'content' => 'Cakes', 'children' => []],
    ], 'Block 2: the id "b_twice" is used twice.'],
]);

it('cleans unsafe text when saving', function () {
    $blocks = withHeading($this->blocks, 'Bread <script>alert(1)</script><strong>daily</strong>');

    $this->actingAs($this->admin)->put(route('admin.easy.pages.update', $this->page), ['blocks' => $blocks])->assertRedirect();
    auth()->logout();

    $this->get('/bakery')->assertDontSee('<script>alert(1)</script>', false)->assertSee('<strong>daily</strong>', false);
});

it('builds every core section with fresh ids and valid blocks', function (string $key) {
    $first = $this->actingAs($this->admin)->getJson(route('admin.easy.sections.build', $key))->assertOk()->json('blocks');
    $second = $this->actingAs($this->admin)->getJson(route('admin.easy.sections.build', $key))->json('blocks');

    expect($first)->not->toBeEmpty()
        ->and(EasyFixtures::blockError($first, $this->admin))->toBeNull()
        ->and(array_intersect(EasyFixtures::ids($first), EasyFixtures::ids($second)))->toBe([]);
})->with(['hero', 'about', 'services', 'gallery', 'testimonials', 'pricing', 'team', 'faq', 'contact', 'map', 'cta']);

it('fills picture sections with the latest pictures of the media library', function () {
    EasyFixtures::fastImages();
    $pictures = collect(['harbour-morning.jpg', 'bread-basket.jpg'])->map(fn (string $name): Media => Media::query()->create([
        'disk' => config('verso.media.disk'), 'path' => 'uploads/'.$name, 'filename' => $name, 'mime_type' => 'image/jpeg',
        'size' => 2048, 'width' => 1200, 'height' => 800, 'alt' => 'Loaves on the counter',
    ]));

    $blocks = $this->actingAs($this->admin)->getJson(route('admin.easy.sections.build', 'gallery'))->json('blocks');
    $gallery = collect($blocks[0]['children'])->firstWhere('type', 'gallery');

    expect($gallery['attrs']['mediaIds'])->toBe([$pictures[1]->id, $pictures[0]->id])
        ->and(EasyFixtures::blockError($blocks, $this->admin))->toBeNull();
});

it('answers not found for unknown sections and forbidden for subscribers', function () {
    $this->actingAs($this->admin)->getJson(route('admin.easy.sections.build', 'fireworks'))->assertNotFound();

    $subscriber = User::factory()->role(Role::Subscriber)->create();
    $this->actingAs($subscriber)->getJson(route('admin.easy.sections.build', 'hero'))->assertForbidden();
});

it('links the admin bar edit button to the visual editor and keeps the advanced editor', function () {
    $this->actingAs($this->admin)->get('/bakery')
        ->assertOk()
        ->assertSee('href="'.route('admin.easy.pages.edit', $this->page).'" data-verso-visual-edit', false)
        ->assertSee('<span class="verso-adminbar__phone-hidden">Edit page</span>', false)
        ->assertSee('href="'.route('admin.entries.edit', ['contentType' => 'page', 'entry' => $this->page->id]).'">Advanced editor</a>', false);
});

it('shows no visual edit button when the user may not change the page', function () {
    Filters::add(EntryCapabilityFilter::class, fn (bool $allowed, User $user, EntryAbility $ability, ?Entry $entry): bool => $ability === EntryAbility::Update ? false : $allowed);

    $this->actingAs($this->admin)->get('/bakery')
        ->assertSee('aria-label="Site administration"', false)
        ->assertDontSee('data-verso-visual-edit', false)
        ->assertDontSee('Advanced editor');
});
