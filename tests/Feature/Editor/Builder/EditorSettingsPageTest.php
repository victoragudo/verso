<?php

use App\Models\User;
use App\Verso\Editor\Builder\EditorAccess;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('shows the editor settings with the default roles', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.editor.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/editor/settings')
            ->where('features.0.key', 'add_panel')
            ->where('features.4.key', 'code_editor')
            ->where('features.4.roles', ['admin', 'editor'])
            ->where('features.4.types', null)
            ->where('contentTypes.0.name', 'post')
            ->where('contentTypes.0.blocks', null)
            ->where('themeBlocks', null)
            ->has('roles', 5)
            ->where('blocks', fn ($blocks) => collect($blocks)->contains('paragraph') && collect($blocks)->contains('section')));
});

it('saves feature roles, content types and block lists', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.editor.settings.update'), [
            'features' => [
                'code_editor' => ['roles' => ['admin'], 'types' => ['page']],
                'custom_js' => ['roles' => [], 'types' => null],
            ],
            'blocks' => ['post' => ['paragraph', 'heading', 'image'], 'page' => null],
        ])
        ->assertRedirect(route('admin.editor.settings.edit'));

    $access = app(EditorAccess::class);
    $editor = User::factory()->editor()->create();

    expect($access->enabled('code_editor', $editor, 'page'))->toBeFalse()
        ->and($access->enabled('code_editor', $this->admin, 'page'))->toBeTrue()
        ->and($access->enabled('code_editor', $this->admin, 'post'))->toBeFalse()
        ->and($access->enabled('custom_js', $this->admin))->toBeFalse()
        ->and($access->allowedBlocks('post'))->toBe(['paragraph', 'heading', 'image'])
        ->and($access->allowedBlocks('page'))->toBeNull();
});

it('rejects unknown roles, features, content types and blocks', function (array $payload, string $error) {
    $this->actingAs($this->admin)
        ->from(route('admin.editor.settings.edit'))
        ->put(route('admin.editor.settings.update'), $payload)
        ->assertSessionHasErrors($error);
})->with([
    'unknown role' => [['features' => ['code_editor' => ['roles' => ['owner'], 'types' => null]], 'blocks' => []], 'features.code_editor.roles.0'],
    'unknown feature' => [['features' => ['teleport' => ['roles' => [], 'types' => null]], 'blocks' => []], 'features'],
    'unknown content type' => [['features' => ['code_editor' => ['roles' => [], 'types' => ['recipe']]], 'blocks' => []], 'features.code_editor.types.0'],
    'unknown block' => [['features' => [], 'blocks' => ['post' => ['marquee']]], 'blocks.post.0'],
]);

it('only lets people who manage settings open the page', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.editor.settings.edit'))
        ->assertForbidden();

    $this->actingAs(User::factory()->editor()->create())
        ->put(route('admin.editor.settings.update'), ['features' => [], 'blocks' => []])
        ->assertForbidden();
});

it('refuses blocks a content type does not allow when saving an entry', function () {
    app(EditorAccess::class)->save([], ['post' => ['paragraph', 'heading']]);

    $this->actingAs($this->admin)
        ->post(route('admin.entries.store', 'post'), [
            'title' => 'Harbour market opening hours',
            'status' => 'draft',
            'blocks' => [
                ['id' => 'b_p1', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Open from nine.</p>'],
                ['id' => 'b_v1', 'type' => 'video', 'attrs' => ['src' => 'https://cdn.example.org/market.mp4']],
            ],
        ])
        ->assertSessionHasErrors(['blocks' => 'Block 2: "video" blocks are turned off here.']);
});

it('sends the editor manifest to the entry editor', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.entries.create', 'page'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('editor.version', 1)
            ->where('editor.allowedBlocks', null)
            ->where('editor.recentLimit', 6)
            ->where('editor.undoSteps', 100)
            ->where('editor.undoMergeMs', 800)
            ->where('coreBlocks.0.name', 'section')
            ->where('coreBlocks.0.children', true)
            ->where('editor.features', fn ($features) => collect($features)->contains('code_editor'))
            ->has('editor.styleProperties'));
});
