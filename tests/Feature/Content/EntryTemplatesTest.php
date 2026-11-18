<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Contracts\EntryPanel;
use App\Verso\Plugins\EntryPanels\EntryPanelRegistry;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create(['name' => 'Sofia Ekman']);
});

describe('editor props', function () {
    it('offers the page templates of the active theme for pages', function () {
        $this->actingAs($this->editor)
            ->get(route('admin.entries.create', 'page'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pageTemplates', [[
                    'value' => 'full-width',
                    'label' => 'Full width',
                    'description' => 'Text uses the whole layout width. Good for landing pages and wide tables.',
                ], [
                    'value' => 'landing',
                    'label' => 'Landing page',
                    'description' => 'Full width page with the minimal header, so visitors stay on the page.',
                ]]));
    });

    it('offers no page templates for types the theme does not list', function () {
        $this->actingAs($this->editor)
            ->get(route('admin.entries.create', 'post'))
            ->assertInertia(fn (Assert $page) => $page->where('pageTemplates', []));
    });

    it('shares the block patterns of the active theme', function () {
        $this->actingAs($this->editor)
            ->get(route('admin.entries.create', 'post'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('blockPatterns', 3)
                ->where('blockPatterns.0.name', 'call-to-action')
                ->where('blockPatterns.0.category', 'Call to action')
                ->where('blockPatterns.0.blocks.2.type', 'button')
                ->where('blockPatterns.1.blocks.0.children.0.type', 'column'));
    });

    it('shares the templates and patterns of a child theme', function () {
        $sandbox = ThemeSandbox::create();
        $sandbox->theme('harbor', [
            'parent' => 'default',
            'templates' => ['landing' => ['label' => 'Landing page', 'types' => ['page', 'post']]],
        ], [
            'patterns/opening-hours.json' => json_encode(['title' => 'Opening hours', 'category' => 'Text', 'blocks' => [['type' => 'paragraph', 'content' => '<p>Open daily.</p>']]]),
        ])->activate('harbor');

        try {
            $this->actingAs($this->editor)
                ->get(route('admin.entries.create', 'page'))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('pageTemplates.0.value', 'full-width')
                    ->where('pageTemplates.1.value', 'landing')
                    ->has('blockPatterns', 4));
        } finally {
            $sandbox->destroy();
        }
    });
});

describe('saving', function () {
    it('saves a template offered by the theme', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'page'), [
                'title' => 'Ferry Timetable',
                'status' => 'draft',
                'meta' => ['template' => 'full-width'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Entry::query()->where('title', 'Ferry Timetable')->first()->meta['template'])->toBe('full-width');
    });

    it('refuses a template the theme does not offer for the type', function (string $type, string $template) {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', $type), [
                'title' => 'Island Hopping in June',
                'status' => 'draft',
                'meta' => ['template' => $template],
            ])
            ->assertSessionHasErrors('meta.template');
    })->with([
        'unknown template' => ['page', 'photo-gallery'],
        'template of another type' => ['post', 'full-width'],
    ]);

    it('refuses a template that is not text', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'page'), [
                'title' => 'Island Hopping in June',
                'status' => 'draft',
                'meta' => ['template' => ['full-width']],
            ])
            ->assertSessionHasErrors('meta.template');
    });

    it('keeps a stored template that the active theme no longer offers', function () {
        $entry = Entry::factory()->page()->by($this->editor)->create(['title' => 'Old Landing', 'meta' => ['template' => 'retired-landing']]);

        $this->actingAs($this->editor)
            ->put(route('admin.entries.update', ['contentType' => 'page', 'entry' => $entry->id]), [
                'title' => 'Old Landing, updated',
                'status' => 'draft',
                'meta' => ['template' => 'retired-landing'],
            ])
            ->assertSessionHasNoErrors();

        expect($entry->fresh()->meta['template'])->toBe('retired-landing');
    });

    it('goes back to the default template when the key is left out', function () {
        $entry = Entry::factory()->page()->by($this->editor)->create(['title' => 'Harbor Rules', 'meta' => ['template' => 'full-width']]);

        $this->actingAs($this->editor)
            ->put(route('admin.entries.update', ['contentType' => 'page', 'entry' => $entry->id]), [
                'title' => 'Harbor Rules',
                'status' => 'draft',
                'meta' => ['comments_open' => false],
            ])
            ->assertSessionHasNoErrors();

        expect($entry->fresh()->meta)->not->toHaveKey('template');
    });
});

it('reserves the template meta key for themes', function () {
    $panel = new class implements EntryPanel
    {
        public function key(): string
        {
            return 'template';
        }

        public function title(): string
        {
            return 'Layout';
        }

        public function description(): string
        {
            return '';
        }

        public function appliesTo(string $type): bool
        {
            return true;
        }

        public function fields(): array
        {
            return [];
        }
    };

    app(EntryPanelRegistry::class)->register($panel);
})->throws(InvalidArgumentException::class, 'reserved');
