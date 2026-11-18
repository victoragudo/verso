<?php

use App\Models\User;
use App\Verso\Editor\Builder\EditorAccess;

it('turns pasted Gutenberg markup into blocks', function () {
    $html = "<!-- wp:heading {\"level\":3} -->\n<h3>Weekend market</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Open from <strong>8 am</strong>.</p>\n<!-- /wp:paragraph -->";

    $response = $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.editor.convert'), ['html' => $html])
        ->assertOk();

    expect(collect($response->json('blocks'))->pluck('type')->all())->toBe(['heading', 'paragraph'])
        ->and($response->json('blocks.0.attrs.level'))->toBe(3)
        ->and($response->json('blocks.1.content'))->toContain('<strong>8 am</strong>');
});

it('turns plain HTML into blocks', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.editor.convert'), ['html' => '<h2>Our story</h2><p>Baking since 1987.</p><ul><li>Bread</li><li>Cakes</li></ul>'])
        ->assertOk();

    expect(collect($response->json('blocks'))->pluck('type')->all())->toBe(['heading', 'paragraph', 'list']);
});

it('needs the code view feature', function () {
    $this->actingAs(User::factory()->author()->create())
        ->postJson(route('admin.editor.convert'), ['html' => '<p>Hi</p>'])
        ->assertForbidden();

    app(EditorAccess::class)->save(['code_editor' => ['roles' => ['admin'], 'types' => null]], []);

    $this->actingAs(User::factory()->editor()->create())
        ->postJson(route('admin.editor.convert'), ['html' => '<p>Hi</p>'])
        ->assertForbidden();
});

it('refuses empty and too long HTML', function () {
    config(['verso.builder.convert_max_length' => 20]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson(route('admin.editor.convert'), ['html' => ''])->assertJsonValidationErrors('html');
    $this->actingAs($admin)->postJson(route('admin.editor.convert'), ['html' => str_repeat('<p>Rye</p>', 5)])->assertJsonValidationErrors('html');
});

it('needs a signed in user', function () {
    $this->postJson(route('admin.editor.convert'), ['html' => '<p>Hi</p>'])->assertUnauthorized();
});
