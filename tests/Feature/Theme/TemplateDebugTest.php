<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Templates\TemplateDebug;
use Illuminate\Http\Response;
use Tests\Support\ThemeSandbox;

beforeEach(function () {
    $this->sandbox = ThemeSandbox::create();
    $this->sandbox->theme('harbor', [], [
        'views/entry-post.blade.php' => "@extends('theme::layouts.app')\n@section('content')<p>Harbor post</p>@endsection\n",
    ])->activate('harbor');

    $this->admin = User::factory()->admin()->create(['name' => 'Ingrid Sundberg']);
    $this->post = Entry::factory()->published()->by($this->admin)->create(['title' => 'Winter Moorings', 'slug' => 'winter-moorings']);
});

afterEach(function () {
    $this->sandbox->destroy();
});

it('shows the checked candidates to admins who ask for it', function () {
    $response = $this->actingAs($this->admin)->get($this->post->path().'?verso-template-debug=1');

    $response->assertOk()
        ->assertSee('id="verso-template-debug"', false)
        ->assertSee('data-template-candidate="entry-post-winter-moorings" data-template-status="missing"', false)
        ->assertSee('data-template-candidate="entry-post-'.$this->post->id.'" data-template-status="missing"', false)
        ->assertSee('data-template-candidate="entry-post" data-template-status="used"', false)
        ->assertSee('data-template-candidate="entry" data-template-status="skipped"', false)
        ->assertSee('harbor/views/entry-post.blade.php')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(strpos($response->getContent(), 'id="verso-template-debug"'))->toBeLessThan(strrpos($response->getContent(), '</body>'));
});

it('shows the lookups of the 404 page', function () {
    $this->actingAs($this->admin)
        ->get('/no-such-mooring?verso-template-debug=1')
        ->assertNotFound()
        ->assertSee('data-template-candidate="404" data-template-status="used"', false)
        ->assertSee('default/views/404.blade.php');
});

it('hides the panel from guests', function () {
    $this->get($this->post->path().'?verso-template-debug=1')
        ->assertOk()
        ->assertDontSee('verso-template-debug"', false);
});

it('hides the panel from users who cannot manage themes', function () {
    $editor = User::factory()->editor()->create();

    $this->actingAs($editor)
        ->get($this->post->path().'?verso-template-debug=1')
        ->assertOk()
        ->assertDontSee('id="verso-template-debug"', false);
});

it('hides the panel without the query string or when it is off', function () {
    $this->actingAs($this->admin)->get($this->post->path())->assertOk()->assertDontSee('id="verso-template-debug"', false);
    $this->actingAs($this->admin)->get($this->post->path().'?verso-template-debug=0')->assertOk()->assertDontSee('id="verso-template-debug"', false);

    config(['verso.themes.template_debug.enabled' => false]);
    app()->forgetScopedInstances();

    $this->actingAs($this->admin)->get($this->post->path().'?verso-template-debug=1')->assertOk()->assertDontSee('id="verso-template-debug"', false);
});

it('uses the query parameter from config', function () {
    config(['verso.themes.template_debug.query' => 'show-templates']);
    app()->forgetScopedInstances();

    $this->actingAs($this->admin)->get($this->post->path().'?show-templates=1')->assertOk()->assertSee('id="verso-template-debug"', false);
});

it('never stores a debug page in the page cache', function () {
    config(['verso.page_cache.enabled' => true]);

    $this->actingAs($this->admin)->get($this->post->path().'?verso-template-debug=1')->assertOk();
    auth()->logout();

    $this->get($this->post->path())->assertOk()->assertDontSee('id="verso-template-debug"', false);
});

it('adds the panel at the end when the page has no body tag', function () {
    $response = new Response('<p>Plain fragment</p>');
    $debug = app(TemplateDebug::class);

    $debug->inject($response);

    expect($response->getContent())->toStartWith('<p>Plain fragment</p><aside id="verso-template-debug"')
        ->and($response->getContent())->toContain('No theme template was looked up for this page.');
});

it('leaves responses that are not HTML alone', function () {
    $response = new Response('{"title":"Winter Moorings"}', 200, ['Content-Type' => 'application/json']);

    app(TemplateDebug::class)->inject($response);

    expect($response->getContent())->toBe('{"title":"Winter Moorings"}');
});
