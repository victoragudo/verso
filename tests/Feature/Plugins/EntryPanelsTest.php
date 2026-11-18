<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Blocks\BlockField;
use App\Verso\Plugins\Contracts\EntryPanel;
use App\Verso\Plugins\EntryPanels\EntryPanelRegistry;
use App\Verso\Plugins\Enums\BlockFieldType;
use Inertia\Testing\AssertableInertia as Assert;

// A panel for posts that stores a sponsor name and a sponsored flag.
function sponsorPanel(string $key = 'sponsor'): EntryPanel
{
    return new class($key) implements EntryPanel
    {
        public function __construct(protected string $panelKey) {}

        public function key(): string
        {
            return $this->panelKey;
        }

        public function title(): string
        {
            return 'Sponsor';
        }

        public function description(): string
        {
            return 'Mark entries paid for by a partner.';
        }

        public function appliesTo(string $contentType): bool
        {
            return $contentType === 'post';
        }

        public function fields(): array
        {
            return [
                new BlockField(name: 'name', label: 'Sponsor name', max: 80),
                new BlockField(name: 'sponsored', label: 'Sponsored', type: BlockFieldType::Toggle, default: false),
            ];
        }
    };
}

beforeEach(function () {
    app(EntryPanelRegistry::class)->register(sponsorPanel());
    $this->editor = User::factory()->editor()->create(['name' => 'Marta Ribeiro']);
});

it('shares the panels of the content type with the editor', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.entries.create', 'post'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pluginPanels', 1)
            ->where('pluginPanels.0.key', 'sponsor')
            ->where('pluginPanels.0.title', 'Sponsor')
            ->where('pluginPanels.0.fields.1.type', 'toggle')
        );
});

it('hides panels that do not apply to the content type', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.entries.create', 'page'))
        ->assertInertia(fn (Assert $page) => $page->where('pluginPanels', []));
});

it('saves panel values in the entry meta next to other meta', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Our trip with Atlantic Ferries',
        'status' => 'draft',
        'meta' => ['sponsor' => ['name' => 'Atlantic Ferries', 'sponsored' => true], 'comments_open' => false],
    ])->assertSessionHasNoErrors();

    $meta = Entry::firstWhere('slug', 'our-trip-with-atlantic-ferries')->meta;

    expect($meta['sponsor'])->toBe(['name' => 'Atlantic Ferries', 'sponsored' => true])
        ->and($meta['comments_open'])->toBeFalse();
});

it('checks panel values with the field rules', function () {
    $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
        'title' => 'Our trip with Atlantic Ferries',
        'status' => 'draft',
        'meta' => ['sponsor' => ['name' => str_repeat('Atlantic ', 20), 'sponsored' => 'perhaps']],
    ])->assertSessionHasErrors(['meta.sponsor.name', 'meta.sponsor.sponsored']);

    expect(Entry::count())->toBe(0);
});

it('lists panels per content type and builds rules', function () {
    $registry = app(EntryPanelRegistry::class);

    expect($registry->forType('post'))->toHaveCount(1)
        ->and($registry->forType('page'))->toBe([])
        ->and($registry->find('sponsor')?->title())->toBe('Sponsor')
        ->and($registry->find('ribbon'))->toBeNull()
        ->and(array_keys($registry->rules('post')))->toBe(['meta.sponsor', 'meta.sponsor.name', 'meta.sponsor.sponsored'])
        ->and($registry->rules('page'))->toBe([]);
});

it('refuses reserved or badly formed panel keys', function (string $key) {
    app(EntryPanelRegistry::class)->register(sponsorPanel($key));
})->with(['seo', 'comments_open', 'Sponsor Box', '9lives'])->throws(InvalidArgumentException::class);
