<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Shortcodes\Enums\ShortcodePlace;
use App\Verso\Shortcodes\ShortcodeContext;
use App\Verso\Shortcodes\ShortcodeExpander;
use App\Verso\Shortcodes\ShortcodeRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PluginFixtures;

require_once __DIR__.'/../Plugins/Forms/Helpers.php';

beforeEach(function () {
    PluginFixtures::boot('forms');
    Mail::fake();
    $this->form = bakeryContactForm();
});

function pageWithParagraph(string $html, array $extra = []): Entry
{
    return Entry::factory()->published()->create([
        'type' => 'page',
        'title' => 'Visit the bakery',
        'slug' => 'visit-the-bakery',
        'blocks' => [['id' => 'p1', 'type' => 'paragraph', 'content' => $html, 'attrs' => [], 'children' => []]],
        ...$extra,
    ]);
}

it('registers the form block as a shortcode with the slug alias', function () {
    $definition = app(ShortcodeRegistry::class)->find('form');

    expect($definition)->not->toBeNull()
        ->and($definition->plugin)->toBe('forms')
        ->and($definition->keyAttribute())->toBe('form')
        ->and($definition->keyOf(['slug' => 'bakery-contact']))->toBe('bakery-contact');
});

it('renders a shortcode alone in a paragraph as the form block, without a wrapping <p>', function () {
    $html = $this->get(pageWithParagraph('[form slug="bakery-contact" title="Ask for a quote"]')->path())->assertOk()->getContent();

    expect($html)->toContain('id="form-bakery-contact"')
        ->toContain('>Ask for a quote</h2>')
        ->not->toContain('<p><form')
        ->not->toContain('[form');
});

it('splits the paragraph around an inline shortcode', function () {
    $html = app(ContentRenderer::class)->render(pageWithParagraph('Questions? <strong>Write</strong> to us: [form slug="bakery-contact"] We answer in a day.'));

    expect($html)->toStartWith('<p>Questions? <strong>Write</strong> to us: </p>')
        ->toContain('id="form-bakery-contact"')
        ->toEndWith('<p> We answer in a day.</p>');
});

it('gives the same HTML for the block, the shortcode and the Blade embed', function () {
    $renderer = app(ContentRenderer::class);
    $block = $renderer->renderBlock(['id' => 'b', 'type' => 'form', 'attrs' => ['form' => 'bakery-contact', 'title' => 'Hi', 'text' => ''], 'content' => '', 'children' => []]);
    $shortcode = app(ShortcodeExpander::class)->expand('[form slug="bakery-contact" title="Hi"]');
    $blade = Blade::render('<x-verso-embed name="form" :attrs="[\'slug\' => \'bakery-contact\', \'title\' => \'Hi\']" />');

    // The form CSS and script are printed once per page and the time token changes, so they are left out.
    $strip = fn (string $html): string => trim((string) preg_replace(['#<(style|script)>.*?</\1>#s', '#name="verso_form_time" value="[^"]*"#'], '', $html));

    expect($strip($shortcode))->toBe($strip($block))
        ->and($strip(trim($blade)))->toBe($strip($block));
});

it('shows the original text to visitors for unknown and invalid shortcodes', function (string $shortcode) {
    $html = $this->get(pageWithParagraph('Before '.$shortcode.' after')->path())->assertOk()->getContent();

    expect($html)->toContain('Before '.$shortcode.' after')
        ->not->toContain('verso-shortcode-warning');
})->with([
    'unknown name' => ['[gallery-slider id="9"]'],
    'missing form' => ['[form slug="no-such-form"]'],
    'unknown attribute' => ['[form slug="bakery-contact" color="red"]'],
]);

it('shows a warning box to admins for broken shortcodes, never cached', function () {
    config(['verso.page_cache.enabled' => true]);
    $entry = pageWithParagraph('Join us [gallery-slider id="9"]');
    $admin = User::factory()->admin()->create(['name' => 'Ana Saraiva']);

    $this->actingAs($admin)->get($entry->path())
        ->assertSee('Shortcode problem (only admins see this): Unknown shortcode', false)
        ->assertSee('verso-shortcode-warning', false);

    auth()->logout();

    $this->get($entry->path())->assertDontSee('verso-shortcode-warning', false)->assertSee('[gallery-slider id="9"]', false);
});

it('does not show the warning to authors without the capability', function () {
    $author = User::factory()->author()->create();
    $html = $this->actingAs($author)->get(pageWithParagraph('[nope]')->path())->getContent();

    expect($html)->not->toContain('verso-shortcode-warning');
});

it('escapes [[form]] so it prints as text', function () {
    $html = app(ShortcodeExpander::class)->expand('Type [[form slug="bakery-contact"]] to embed it.');

    expect($html)->toBe('Type [form slug="bakery-contact"] to embed it.');
});

it('stops XSS in attribute values', function (string $shortcode) {
    $html = $this->get(pageWithParagraph($shortcode)->path())->assertOk()->getContent();
    $body = str($html)->after('<main')->before('</main>')->toString();

    expect($body)->not->toContain('<script>alert')
        ->not->toContain('onerror="')
        ->not->toContain('<img src=x')
        ->not->toContain('href="javascript:');
})->with([
    'script in title' => ['[form slug="bakery-contact" title="<script>alert(1)</script>"]'],
    'quote breaking out' => ['[form slug="bakery-contact" title=\'" onerror="alert(1)\']'],
    'script after the shortcode' => ['[form slug="bakery-contact"]<script>alert(1)</script>'],
    'img in text' => ['[form slug="bakery-contact" text="<img src=x onerror=alert(1)>"]'],
    'javascript link' => ['<a href="javascript:alert(1)">[form slug="bakery-contact"]</a>'],
    'script as slug' => ['[form slug="<script>alert(1)</script>"]'],
]);

it('escapes the heading text given in a shortcode', function () {
    $html = app(ShortcodeExpander::class)->expand('[form slug="bakery-contact" title="Cakes &amp; <b>pies</b>"]');

    expect($html)->toContain('Cakes &amp; &lt;b&gt;pies&lt;/b&gt;');
});

it('validates attrs against the block fields', function () {
    $definition = app(ShortcodeRegistry::class)->find('form');

    expect($definition->validate(['slug' => 'bakery-contact'])['errors'])->toBe([])
        ->and($definition->validate(['slug' => 'bakery-contact', 'title' => str_repeat('a', 121)])['errors'])->not->toBe([])
        ->and($definition->validate(['slug' => 'pizza'])['errors'][0])->toContain('form')
        ->and($definition->validate(['slug' => 'bakery-contact', 'size' => 'xl'])['errors'])->toBe(['Unknown attribute "size".']);
});

it('runs in opt-in places only when the place is turned on', function () {
    $expander = app(ShortcodeExpander::class);
    $context = ShortcodeContext::for(ShortcodePlace::Footer);

    expect($expander->expand('[form slug="bakery-contact"]', $context))->toContain('id="form-bakery-contact"');

    config(['verso.shortcodes.places' => ['excerpt']]);

    expect($expander->expand('[form slug="bakery-contact"]', $context))->toBe('[form slug="bakery-contact"]');
});

it('escapes plain text in the @shortcodes directive and expands its shortcodes', function () {
    $html = Blade::render("@shortcodes(\$text, 'excerpt')", ['text' => '<b>Hi</b> [form slug="bakery-contact"]']);

    expect($html)->toStartWith('&lt;b&gt;Hi&lt;/b&gt; ')->toContain('id="form-bakery-contact"');
});

it('does nothing when shortcodes are turned off', function () {
    config(['verso.shortcodes.enabled' => false]);

    expect(app(ShortcodeExpander::class)->expand('[form slug="bakery-contact"]'))->toBe('[form slug="bakery-contact"]');
});

it('prints a warning for an unknown embed name in Blade only to admins', function () {
    expect(trim(Blade::render('<x-verso-embed name="map" />')))->toBe('');

    $this->actingAs(User::factory()->admin()->create());

    expect(Blade::render('<x-verso-embed name="map" />'))->toContain('Unknown embed');
});

it('keeps entries with shortcodes out of the block cache', function () {
    $renderer = app(ContentRenderer::class);

    expect($renderer->hasShortcodes([['type' => 'paragraph', 'content' => 'Hi [form slug="a"]']]))->toBeTrue()
        ->and($renderer->hasShortcodes([['type' => 'columns', 'children' => [['type' => 'paragraph', 'content' => '[x]']]]]))->toBeTrue()
        ->and($renderer->hasShortcodes([['type' => 'paragraph', 'content' => 'Prices [in euros']]))->toBeFalse();
});

it('stops shortcodes over the per text limit', function () {
    config(['verso.shortcodes.max_per_text' => 1]);
    $html = app(ShortcodeExpander::class)->expand('[form slug="bakery-contact"] [form slug="bakery-contact"]');

    expect(substr_count($html, 'id="form-bakery-contact"'))->toBe(1)
        ->and($html)->toEndWith(' [form slug="bakery-contact"]');
});
