<?php

use App\Models\User;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Contracts\EmbeddableBlock;
use App\Verso\Plugins\Contracts\HasBlockFields;
use App\Verso\Plugins\Contracts\PluginBlock;
use App\Verso\Shortcodes\Models\EmbedUsage;
use App\Verso\Shortcodes\Usage\EmbedInvalidator;
use App\Verso\Shortcodes\Usage\UsageIndex;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\Support\PluginFixtures;
use Verso\Plugins\Forms\Actions\SaveForm;
use Verso\Plugins\Forms\Models\Form;

require_once __DIR__.'/../Plugins/Forms/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    Mail::fake();
    $this->form = bakeryContactForm();
});

function savedEntry(array $attributes): Entry
{
    $entry = Entry::factory()->published()->create($attributes);
    EntrySaved::dispatch($entry);

    return $entry;
}

function usageRows(Entry $entry): array
{
    return EmbedUsage::query()->where('entry_id', $entry->id)->orderBy('kind')->orderBy('name')->get()
        ->map(fn (EmbedUsage $usage): string => $usage->kind->value.':'.$usage->name.':'.($usage->key ?? '-'))->all();
}

it('indexes plugin blocks, shortcodes in nested text and the excerpt when an entry is saved', function () {
    $entry = savedEntry([
        'title' => 'Wedding cakes',
        'excerpt' => 'Ask us with [form slug="bakery-contact"]',
        'blocks' => [
            ['id' => 'b1', 'type' => 'form', 'attrs' => ['form' => 'catering'], 'content' => '', 'children' => []],
            ['id' => 'b2', 'type' => 'columns', 'attrs' => [], 'content' => '', 'children' => [
                ['id' => 'b3', 'type' => 'column', 'attrs' => [], 'content' => '', 'children' => [
                    ['id' => 'b4', 'type' => 'paragraph', 'content' => 'Or [form slug="bakery-contact"] and [old-slider id=3]', 'attrs' => [], 'children' => []],
                ]],
            ]],
        ],
    ]);

    expect(usageRows($entry))->toBe([
        'block:form:catering',
        'shortcode:form:bakery-contact',
        'shortcode:old-slider:-',
    ]);
});

it('replaces the rows of an entry on every save and drops them when the entry is deleted', function () {
    $entry = savedEntry(['blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="bakery-contact"]', 'attrs' => [], 'children' => []]]]);

    $entry->update(['blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => 'No forms here.', 'attrs' => [], 'children' => []]]]);
    EntrySaved::dispatch($entry);
    expect(usageRows($entry))->toBe([]);

    $entry->update(['blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="bakery-contact"]', 'attrs' => [], 'children' => []]]]);
    EntrySaved::dispatch($entry);
    $entry->forceDelete();

    expect(EmbedUsage::query()->count())->toBe(0);
});

it('finds the entries that embed a form by slug', function () {
    $cakes = savedEntry(['title' => 'Cakes', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="bakery-contact"]', 'attrs' => [], 'children' => []]]]);
    savedEntry(['title' => 'Bread', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="other"]', 'attrs' => [], 'children' => []]]]);

    expect(app(UsageIndex::class)->entriesUsing('form', ['bakery-contact'])->pluck('title')->all())->toBe(['Cakes']);
});

it('clears only the cached pages that embed a form when it is saved', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    app()->forgetInstance(EmbedInvalidator::class);

    $withForm = savedEntry(['type' => 'page', 'slug' => 'order-a-cake', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => 'Order: [form slug="bakery-contact"]', 'attrs' => [], 'children' => []]]]);
    $without = savedEntry(['type' => 'page', 'slug' => 'opening-hours', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => 'Open 7:00 to 19:00.', 'attrs' => [], 'children' => []]]]);

    foreach ([$withForm, $without] as $entry) {
        $this->get($entry->path())->assertHeader(PageCache::HEADER, 'MISS');
        $this->get($entry->path())->assertHeader(PageCache::HEADER, 'HIT');
    }

    app(SaveForm::class)->handle([
        'title' => 'Contact the bakery',
        'slug' => 'bakery-contact',
        'fields' => $this->form->fields,
        'settings' => [...$this->form->settings, 'submit_label' => 'Send to Marta'],
    ], $this->form);

    $this->get($withForm->path())->assertHeader(PageCache::HEADER, 'MISS')->assertSee('Send to Marta');
    $this->get($without->path())->assertHeader(PageCache::HEADER, 'HIT');
});

it('clears pages that used the old slug when a form is renamed', function () {
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);
    app()->forgetInstance(EmbedInvalidator::class);
    $entry = savedEntry(['type' => 'page', 'slug' => 'contact-us', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="bakery-contact"]', 'attrs' => [], 'children' => []]]]);
    $this->get($entry->path());
    $this->get($entry->path())->assertHeader(PageCache::HEADER, 'HIT');

    app(SaveForm::class)->handle(['title' => 'Contact', 'slug' => 'padaria-contact', 'fields' => $this->form->fields, 'settings' => $this->form->settings], $this->form);

    $this->get($entry->path())->assertHeader(PageCache::HEADER, 'MISS');
});

it('clears every page when targeted invalidation is off', function () {
    config(['verso.page_cache.enabled' => true, 'verso.shortcodes.targeted_invalidation' => false]);
    app()->forgetInstance(PageCache::class);
    app()->forgetInstance(EmbedInvalidator::class);
    $entry = savedEntry(['type' => 'page', 'slug' => 'opening-hours', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => 'Open daily.', 'attrs' => [], 'children' => []]]]);
    $this->get($entry->path());

    app(EmbedInvalidator::class)->changed('form', 'bakery-contact');

    $this->get($entry->path())->assertHeader(PageCache::HEADER, 'MISS');
});

it('lists the entries that use a form on the admin edit page', function () {
    savedEntry(['title' => 'Order a cake', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="bakery-contact"]', 'attrs' => [], 'children' => []]]]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.forms.edit', $this->form))
        ->assertInertia(fn ($page) => $page
            ->where('usedIn.0.title', 'Order a cake')
            ->where('shortcode', '[form slug="bakery-contact"]')
            ->where('bladeSnippet', '<x-verso-embed name="form" :attrs="[\'slug\' => \'bakery-contact\']" />'));
});

it('checks the block capability for shortcodes in text', function () {
    $block = new class implements EmbeddableBlock, HasBlockFields, PluginBlock
    {
        public function name(): string
        {
            return 'raw-map';
        }

        public function label(): string
        {
            return 'Map';
        }

        public function description(): string
        {
            return 'A raw map embed.';
        }

        public function fields(): array
        {
            return [new BlockField(name: 'place', label: 'Place')];
        }

        public function render(array $block, ?Entry $entry = null): string
        {
            return '<div class="map"></div>';
        }

        public function shortcode(): bool
        {
            return true;
        }

        public function keyAttribute(): ?string
        {
            return 'place';
        }

        public function attributeAliases(): array
        {
            return [];
        }

        public function capability(): ?string
        {
            return 'manage_settings';
        }
    };
    app(PluginBlockRegistry::class)->register($block, 'maps');
    app(BlockRegistry::class)->register(new PluginBlockAdapter($block));

    $blocks = [['id' => 'p1', 'type' => 'paragraph', 'content' => 'Find us: [raw-map place="Lumiar"]', 'attrs' => [], 'children' => []]];
    $check = fn (User $user) => Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks($user)]]);

    expect($check(User::factory()->author()->create())->errors()->first())->toContain('not allowed to add "raw-map" shortcodes')
        ->and($check(User::factory()->admin()->create())->passes())->toBeTrue();
});

it('lists shortcodes and usage with orphans in the console', function () {
    savedEntry(['title' => 'Old post', 'blocks' => [['id' => 'p', 'type' => 'paragraph', 'content' => '[form slug="bakery-contact"] [contact-form-7 id="12"]', 'attrs' => [], 'children' => []]]]);

    $this->artisan('verso:shortcodes')
        ->expectsOutputToContain('| [form]    | forms  | form*, title, text |')
        ->assertSuccessful();

    $this->artisan('verso:shortcodes', ['--usage' => true])
        ->expectsOutputToContain('Orphan shortcodes (used but not registered): contact-form-7')
        ->assertSuccessful();
});

it('keeps the form list in sync when a form is deleted', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.forms.destroy', $this->form))
        ->assertRedirect();

    expect(Form::query()->count())->toBe(0);
});
