<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Editor\CustomCode\CustomCode;
use App\Verso\Editor\CustomCode\CustomCodeStore;
use App\Verso\Editor\CustomCode\StylesheetSanitizer;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('keeps normal stylesheets', function () {
    $css = ".hero { padding: 4rem 1rem; background: url(/img/bread.jpg) center / cover; }\n@media (max-width: 640px) { .hero h1 { font-size: 2rem; } }\n.menu > li:hover { color: #1f6f5c; }";

    expect(app(StylesheetSanitizer::class)->clean($css))->toBe($css);
});

it('removes dangerous parts of a stylesheet', function (string $css, string $expected) {
    expect(app(StylesheetSanitizer::class)->clean($css))->toBe($expected);
})->with([
    'closing style tag' => ['.a { color: red; }</style><script>alert(1)</script>', '.a { color: red; }/style>script>alert(1)/script>'],
    'import' => ['@import url(https://evil.example/x.css); .a { color: red; }', '.a { color: red; }'],
    'javascript url' => ['.a { background: url(javascript:alert(1)); color: blue; }', '.a { color: blue; }'],
    'expression' => ['.a { width: expression(alert(1)); }', '.a { }'],
    'behavior' => ['.a { behavior: url(x.htc); -moz-binding: url(x.xml#y); margin: 0; }', '.a { margin: 0; }'],
    'comments' => ['/* hide */ .a { color: red; }', '.a { color: red; }'],
    'stray brace' => ['} .a { color: red; ', '.a { color: red; }'],
]);

it('saves site wide code and shows it on public pages', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.editor.code.update'), ['css' => 'body { accent-color: #1f6f5c; }', 'js' => "console.log('Harbor Bakery');"])
        ->assertRedirect(route('admin.editor.code.edit'));

    $code = app(CustomCodeStore::class)->find(null);
    $html = $this->get('/')->assertOk()->getContent();

    expect($code?->css)->toBe('body { accent-color: #1f6f5c; }')
        ->and($html)->toContain('<style>body { accent-color: #1f6f5c; }</style>')
        ->and($html)->toContain('src="'.e(route('verso.custom-script', ['scope' => 'site', 'v' => $code?->version()])).'" defer></script>')
        ->and($html)->not->toContain("console.log('Harbor Bakery')");

    $this->get(route('verso.custom-script', ['scope' => 'site']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertSeeText("console.log('Harbor Bakery');", false);
});

it('saves code for one page and prints it only there', function () {
    $page = Entry::factory()->page()->published()->create(['title' => 'Weekend market', 'slug' => 'weekend-market']);
    $other = Entry::factory()->page()->published()->create(['title' => 'About us', 'slug' => 'about-us']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.editor.code.entry.update', $page), ['css' => '.stall { border: 1px solid #ccc; }', 'js' => 'document.body.dataset.market = "on";'])
        ->assertOk()
        ->assertJson(['css' => '.stall { border: 1px solid #ccc; }']);

    $this->actingAs($this->admin)->getJson(route('admin.editor.code.entry', $page))
        ->assertOk()
        ->assertJson(['can' => ['css' => true, 'js' => true], 'js' => 'document.body.dataset.market = "on";']);

    auth()->logout();
    $html = $this->get('/weekend-market')->assertOk()->getContent();

    expect($html)->toContain('<style data-verso-page-css>.stall { border: 1px solid #ccc; }</style>')
        ->and($html)->toContain('verso-code/entry-'.$page->id.'.js')
        ->and($this->get('/about-us')->getContent())->not->toContain('data-verso-page-css');
    $this->get(route('verso.custom-script', ['scope' => 'entry-'.$page->id]))->assertOk();
    $this->get(route('verso.custom-script', ['scope' => 'entry-'.$other->id]))->assertNotFound();
});

it('hides the script of a draft from visitors', function () {
    $draft = Entry::factory()->page()->create(['title' => 'Secret launch']);
    app(CustomCodeStore::class)->save($draft, null, 'launch();', $this->admin);

    $this->get(route('verso.custom-script', ['scope' => 'entry-'.$draft->id]))->assertNotFound();
    $this->actingAs($this->admin)->get(route('verso.custom-script', ['scope' => 'entry-'.$draft->id]))->assertOk();
});

it('only lets admins change custom code', function () {
    $editor = User::factory()->editor()->create();
    $page = Entry::factory()->page()->create(['author_id' => $editor->id]);

    $this->actingAs($editor)->get(route('admin.editor.code.edit'))->assertForbidden();
    $this->actingAs($editor)->putJson(route('admin.editor.code.entry.update', $page), ['css' => 'a{}'])->assertForbidden();
});

it('saves only the parts the feature settings allow', function () {
    app(EditorAccess::class)->save(['custom_js' => ['roles' => [], 'types' => null]], []);
    app(CustomCodeStore::class)->save(null, null, 'keep();', $this->admin);

    $this->actingAs($this->admin)
        ->get(route('admin.editor.code.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/editor/custom-code')->where('can', ['css' => true, 'js' => false]));

    $this->actingAs($this->admin)
        ->put(route('admin.editor.code.update'), ['css' => 'h1 { color: navy; }', 'js' => 'steal();'])
        ->assertRedirect();

    expect(app(CustomCodeStore::class)->find(null)?->js)->toBe('keep();');
});

it('removes the row when both parts are empty', function () {
    app(CustomCodeStore::class)->save(null, 'h1 { color: navy; }', '', $this->admin);
    app(CustomCodeStore::class)->save(null, '', '', $this->admin);

    expect(CustomCode::query()->count())->toBe(0);
});

it('refuses code that is too long', function () {
    config(['verso.builder.custom_code.js_max' => 10]);

    $this->actingAs($this->admin)
        ->from(route('admin.editor.code.edit'))
        ->put(route('admin.editor.code.update'), ['css' => '', 'js' => str_repeat('a', 11)])
        ->assertSessionHasErrors('js');
});
